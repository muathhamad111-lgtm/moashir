import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { withSetup } from '@/test-utils/withSetup'
import { useTransferDecisions } from '@/composables/useTransferDecisions'
import { useBankTransferReview } from '@/composables/useBankTransferReview'
import { bankTransfersService } from '@/services/bankTransfersService'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import { BANK_TRANSFER_CODES } from '@/constants/bankTransfer'

vi.mock('@/services/bankTransfersService', () => ({
  bankTransfersService: {
    get: vi.fn(),
    approve: vi.fn(),
    reject: vi.fn(),
    requestProofDownload: vi.fn(),
  },
}))

const TRANSFER = {
  id: 'pay-26',
  reference: 'PAY-2026-000026',
  status: 'under_review',
  entity: { id: 5001, name: { ar: 'جمعية البر بالرياض' }, isFrozen: false },
  pricing: { total: '1150.00', currency: 'SAR' },
  reviewedAt: null,
}
const APPROVED = { ...TRANSFER, status: 'paid', reviewedAt: '2026-11-06T11:07:00Z' }

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useTransferDecisions', () => {
  it('closes the approve dialog once a single decision succeeds', async () => {
    bankTransfersService.approve.mockResolvedValue({ data: APPROVED })
    const onDecided = vi.fn()
    const { api } = withSetup(() => useTransferDecisions({ onDecided }))
    api.openApprove([TRANSFER])
    expect(api.approveOpen.value).toBe(true)
    await api.approve()
    expect(api.approveOpen.value).toBe(false)
    expect(onDecided).toHaveBeenCalledWith({ transfer: APPROVED })
  })

  it('closes the reject dialog when the transfer was already decided elsewhere', async () => {
    bankTransfersService.reject.mockRejectedValue({
      response: { status: 409 },
      normalized: { code: BANK_TRANSFER_CODES.ALREADY_DECIDED, message: '' },
    })
    const { api } = withSetup(() => useTransferDecisions())
    api.openReject([TRANSFER])
    await api.reject({ rejectionReason: 'not_received', note: '' })
    expect(api.rejectOpen.value).toBe(false)
    expect(api.conflict.value).toBe(BANK_TRANSFER_CODES.ALREADY_DECIDED)
  })
})

describe('useBankTransferReview', () => {
  it('reloads silently after a decision so the page and its dialog stay mounted', async () => {
    bankTransfersService.get
      .mockResolvedValueOnce({ data: TRANSFER })
      .mockResolvedValueOnce({ data: APPROVED })
    bankTransfersService.approve.mockResolvedValue({ data: APPROVED })
    const { api, globals } = withSetup(() => useBankTransferReview('pay-26'))
    useAuthStore(globals.pinia).setUser(sessionUserFor(['finance_officer']))
    await api.load()
    api.decisions.openApprove([api.transfer.value])
    await api.decisions.approve()
    const afterDecision = api.status.value
    await flushPromises()
    expect([afterDecision, api.status.value]).toEqual(['success', 'success'])
    expect(api.decisions.approveOpen.value).toBe(false)
    expect(api.transfer.value.status).toBe('paid')
  })
})
