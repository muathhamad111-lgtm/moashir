import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { ref } from 'vue'
import { withSetup } from '@/test-utils/withSetup'
import { useTransferSelection } from '@/composables/useTransferSelection'
import { SUBMIT_FAILURES, useBankTransferSubmission } from '@/composables/useBankTransferSubmission'
import { CHECKOUT_STEPS, initialStepOf } from '@/composables/useCheckoutChannels'
import {
  CHANNEL_BLOCKERS,
  channelsOfChoice,
  choiceOf,
  usePaymentChannelsSettings,
} from '@/composables/usePaymentChannelsSettings'
import { movedIds } from '@/composables/useBankAccountsSettings'
import { paymentsService } from '@/services/paymentsService'
import { paymentChannelsService } from '@/services/paymentChannelsService'
import { BULK_DECISION_MAX, PROOF_STATES } from '@/constants/bankTransfer'

vi.mock('@/services/paymentsService', () => ({ paymentsService: { submitTransfer: vi.fn() } }))
vi.mock('@/services/paymentChannelsService', () => ({
  paymentChannelsService: { getChannels: vi.fn(), updateChannels: vi.fn() },
}))

const ACCOUNTS = [
  { id: 'acc-1', bankName: { ar: 'بنك المثال' }, iban: 'SA0380000000608010167519' },
  { id: 'acc-2', bankName: { ar: 'مصرف العيّنة' }, iban: 'SA5300000000001234567890' },
]
const PDF = new File(['%PDF'], 'receipt.pdf', { type: 'application/pdf' })

function domainError(code, meta = {}) {
  return { response: { status: 409 }, normalized: { code, meta, message: code } }
}

function channelsView(overrides = {}) {
  return {
    channels: ['electronic'],
    availableChannels: ['electronic'],
    gatewayReady: true,
    activeBankAccountsCount: 1,
    reviewersCount: 1,
    ...overrides,
  }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useTransferSelection', () => {
  it('selects one, all, and only the decidable rows, and drops rows that leave the list', async () => {
    const items = ref([
      { id: 'a', frozen: false },
      { id: 'b', frozen: true },
      { id: 'c', frozen: false },
    ])
    const { api } = withSetup(() =>
      useTransferSelection(items, { isSelectable: (item) => !item.frozen }),
    )
    api.toggle('b')
    expect(api.count.value).toBe(0)
    api.toggle('a')
    expect([api.someSelected.value, api.allSelected.value]).toEqual([true, false])
    api.toggleAll()
    expect(api.selectedIds.value).toEqual(['a', 'c'])
    expect(api.allSelected.value).toBe(true)
    items.value = [{ id: 'c', frozen: false }]
    await flushPromises()
    expect(api.selectedIds.value).toEqual(['c'])
    api.clear()
    expect(api.count.value).toBe(0)
  })

  it('flags a selection above the bulk limit', () => {
    const items = ref(
      Array.from({ length: BULK_DECISION_MAX + 1 }, (_, index) => ({ id: `p-${index}` })),
    )
    const { api } = withSetup(() => useTransferSelection(items))
    api.toggleAll(true)
    expect(api.overLimit.value).toBe(true)
  })
})

describe('useBankTransferSubmission', () => {
  function setup() {
    const onSubmitted = vi.fn()
    const { api } = withSetup(() =>
      useBankTransferSubmission(ref({ purpose: 'accredited', awardCycleId: null }), ref(ACCOUNTS), {
        onSubmitted,
      }),
    )
    return { api, onSubmitted }
  }

  it('starts on the first account and refuses a wrong file before sending', async () => {
    const { api } = setup()
    expect(api.accountId.value).toBe('acc-1')
    api.selectProof(new File(['x'], 'receipt.docx'))
    expect(api.proofState.value).toBe(PROOF_STATES.WRONG_TYPE)
    expect(api.canSend.value).toBe(false)
    await api.submit()
    expect(paymentsService.submitTransfer).not.toHaveBeenCalled()
    api.selectProof(null)
    await api.submit()
    expect(api.missingProof.value).toBe(true)
  })

  it('sends the account and receipt once with an Idempotency-Key, through scanning to accepted', async () => {
    const { api, onSubmitted } = setup()
    paymentsService.submitTransfer.mockImplementation((order, key, { onUploadProgress }) => {
      onUploadProgress({ loaded: 50, total: 100 })
      expect(api.progress.value).toBe(50)
      onUploadProgress({ loaded: 100, total: 100 })
      expect(api.proofState.value).toBe(PROOF_STATES.SCANNING)
      return Promise.resolve({ data: { id: 'p-9', status: 'under-review' } })
    })
    api.selectAccount('acc-2')
    api.selectProof(PDF)
    expect(api.proofState.value).toBe(PROOF_STATES.READY)
    const first = api.submit()
    await api.submit()
    await first
    expect(paymentsService.submitTransfer).toHaveBeenCalledTimes(1)
    const [order, key] = paymentsService.submitTransfer.mock.calls[0]
    expect(order).toEqual({
      purpose: 'accredited',
      awardCycleId: null,
      bankAccountId: 'acc-2',
      proof: PDF,
    })
    expect(key).toMatch(/^[0-9a-f-]{36}$/)
    expect(api.proofState.value).toBe(PROOF_STATES.ACCEPTED)
    expect(onSubmitted).toHaveBeenCalledWith({ id: 'p-9', status: 'under-review' })
  })

  it('maps the server refusals to receipt states and failures', async () => {
    const { api } = setup()
    api.selectProof(PDF)
    paymentsService.submitTransfer.mockRejectedValueOnce(domainError('file.infected'))
    await api.submit()
    expect(api.proofState.value).toBe(PROOF_STATES.UNSAFE)
    api.selectProof(PDF)
    paymentsService.submitTransfer.mockRejectedValueOnce(domainError('bank_account.not_selectable'))
    await api.submit()
    expect(api.failure.value.kind).toBe(SUBMIT_FAILURES.ACCOUNT)
    expect(api.unavailableAccountId.value).toBe('acc-1')
    expect(api.canSend.value).toBe(false)
    api.selectAccount('acc-2')
    paymentsService.submitTransfer.mockRejectedValueOnce(
      domainError('payment.open_order_exists', { paymentId: 'p-1', channel: 'electronic' }),
    )
    await api.submit()
    expect(api.failure.value).toMatchObject({
      kind: SUBMIT_FAILURES.OPEN_ORDER,
      meta: { paymentId: 'p-1', channel: 'electronic' },
    })
  })

  it('keeps the same key and offers a retry after a network failure', async () => {
    const { api } = setup()
    api.selectProof(PDF)
    paymentsService.submitTransfer
      .mockRejectedValueOnce(new Error('network'))
      .mockResolvedValueOnce({ data: { id: 'p-9' } })
    await api.submit()
    expect(api.proofState.value).toBe(PROOF_STATES.FAILED)
    await api.submit()
    const keys = paymentsService.submitTransfer.mock.calls.map((call) => call[1])
    expect(keys[0]).toBe(keys[1])
  })
})

describe('checkout channel step', () => {
  it('skips the choice with one channel and shows unavailable with none', () => {
    expect(initialStepOf([])).toBe(CHECKOUT_STEPS.UNAVAILABLE)
    expect(initialStepOf(['bank_transfer'])).toBe(CHECKOUT_STEPS.BANK_TRANSFER)
    expect(initialStepOf(['electronic'])).toBe(CHECKOUT_STEPS.ELECTRONIC)
    expect(initialStepOf(['electronic', 'bank_transfer'])).toBe(CHECKOUT_STEPS.CHOOSE)
  })
})

describe('payment channels settings', () => {
  it('maps the stored channels to the three choices and back', () => {
    expect(choiceOf(['electronic', 'bank_transfer'])).toBe('both')
    expect(choiceOf(['bank_transfer'])).toBe('bank_transfer')
    expect(choiceOf(['electronic'])).toBe('electronic')
    expect(channelsOfChoice('both')).toEqual(['electronic', 'bank_transfer'])
  })

  it('blocks saving without an active account or a ready gateway and warns without a reviewer', async () => {
    paymentChannelsService.getChannels.mockResolvedValue({
      data: channelsView({ activeBankAccountsCount: 0, gatewayReady: false, reviewersCount: 0 }),
    })
    const { api } = withSetup(() =>
      usePaymentChannelsSettings({ reasonExtra: () => Promise.resolve({}) }),
    )
    await api.load()
    api.select('bank_transfer')
    expect(api.blocker.value).toBe(CHANNEL_BLOCKERS.NO_ACCOUNT)
    expect(api.noReviewer.value).toBe(true)
    expect(api.canSave.value).toBe(false)
    api.select('electronic')
    expect(api.blocker.value).toBe(CHANNEL_BLOCKERS.GATEWAY_NOT_READY)
  })

  it('saves the chosen channels with the Super Admin reason and stops when it is cancelled', async () => {
    paymentChannelsService.getChannels.mockResolvedValue({ data: channelsView() })
    paymentChannelsService.updateChannels.mockResolvedValue({
      data: channelsView({ channels: ['electronic', 'bank_transfer'] }),
    })
    const reasonExtra = vi
      .fn()
      .mockResolvedValueOnce(null)
      .mockResolvedValueOnce({ reason: 'Reason for the audit log.' })
    const { api } = withSetup(() => usePaymentChannelsSettings({ reasonExtra }))
    await api.load()
    api.select('both')
    await api.save()
    expect(paymentChannelsService.updateChannels).not.toHaveBeenCalled()
    await api.save()
    expect(paymentChannelsService.updateChannels).toHaveBeenCalledWith(
      ['electronic', 'bank_transfer'],
      'Reason for the audit log.',
    )
    expect(api.dirty.value).toBe(false)
  })

  it('moves an account up or down and refuses moves past the ends', () => {
    const accounts = [{ id: 'a' }, { id: 'b' }, { id: 'c' }]
    expect(movedIds(accounts, 'b', 'up')).toEqual(['b', 'a', 'c'])
    expect(movedIds(accounts, 'b', 'down')).toEqual(['a', 'c', 'b'])
    expect(movedIds(accounts, 'a', 'up')).toBeNull()
    expect(movedIds(accounts, 'c', 'down')).toBeNull()
  })
})
