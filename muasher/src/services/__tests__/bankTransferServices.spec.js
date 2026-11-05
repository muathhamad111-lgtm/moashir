import { beforeEach, describe, expect, it, vi } from 'vitest'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'
import { paymentsService } from '@/services/paymentsService'
import { paymentChannelsService } from '@/services/paymentChannelsService'
import { bankTransfersService } from '@/services/bankTransfersService'
import {
  pendingTransfersOf,
  toBankAccount,
  toCheckoutOptions,
  toPaymentChannels,
  toStaffTransfer,
} from '@/services/mappers/bankTransfers'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const KEY = '7b0f5a2e-1c3d-4e5f-8a9b-0c1d2e3f4a5b'
const ACCOUNT = {
  id: 'acc-1',
  bankName: { ar: 'بنك المثال', en: 'Example Bank' },
  accountHolderName: { ar: 'منصة مؤشر', en: 'Moashir Platform' },
  iban: 'SA0380000000608010167519',
}

function envelope(data, meta = {}) {
  return { data: { data, meta, errors: null } }
}

function rejection(status) {
  return Object.assign(new Error('rejected'), { response: { status } })
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('bank-transfer mappers', () => {
  it('keeps known channels only and defaults the counters', () => {
    expect(toPaymentChannels({ channels: ['electronic', 'cash'], gatewayReady: true })).toEqual({
      channels: ['electronic'],
      availableChannels: [],
      gatewayReady: true,
      activeBankAccountsCount: 0,
      reviewersCount: 0,
    })
  })

  it('shapes accounts, checkout options and staff transfers with safe defaults', () => {
    expect(toBankAccount({ ...ACCOUNT, isActive: true })).toMatchObject({
      ...ACCOUNT,
      accountNumber: null,
      payerNote: null,
      isActive: true,
      isUsed: false,
      pendingTransfersCount: 0,
      createdBy: null,
    })
    expect(
      toCheckoutOptions({
        channels: ['bank_transfer'],
        bankAccounts: [ACCOUNT],
        transferNote: { unifiedNumber: '7000000000' },
      }),
    ).toEqual({
      channels: ['bank_transfer'],
      bankAccounts: [{ ...ACCOUNT, accountNumber: null, payerNote: null }],
      unifiedNumber: '7000000000',
    })
    const staff = toStaffTransfer({ id: 'p-1', entity: { name: { ar: 'جمعية' } } })
    expect(staff.entity).toEqual({
      id: null,
      reference: '',
      name: { ar: 'جمعية', en: '' },
      email: null,
      isFrozen: false,
    })
    expect(staff.duplicateProofs).toBeNull()
  })

  it('reads the pending transfers of a blocked account from the error meta', () => {
    const error = {
      normalized: {
        meta: {
          transfers: [{ paymentId: 'p-1', reference: 'PAY-2026-000021', total: '1150.00' }],
        },
      },
    }
    expect(pendingTransfersOf(error)).toEqual([
      {
        paymentId: 'p-1',
        reference: 'PAY-2026-000021',
        entityName: { ar: '', en: '' },
        total: '1150.00',
        currency: 'SAR',
        submittedAt: null,
      },
    ])
    expect(pendingTransfersOf({})).toEqual([])
  })
})

describe('paymentsService bank transfer calls', () => {
  it('sends the transfer as multipart with the Idempotency-Key and maps the transfer block', async () => {
    apiClient.post.mockResolvedValue(
      envelope({ id: 'p-1', bankTransfer: { bankAccount: ACCOUNT, proof: { name: 'r.pdf' } } }),
    )
    const proof = new File(['%PDF'], 'r.pdf', { type: 'application/pdf' })
    const progress = vi.fn()
    const { data } = await paymentsService.submitTransfer(
      { purpose: 'bag', bankAccountId: 'acc-1', proof },
      KEY,
      { onUploadProgress: progress },
    )
    const [path, form, config] = apiClient.post.mock.calls[0]
    expect(path).toBe('/payments')
    expect(Object.fromEntries(form.entries())).toEqual({
      purpose: 'bag',
      channel: 'bank_transfer',
      bankAccountId: 'acc-1',
      proof: expect.any(File),
    })
    expect(config).toEqual({ headers: { 'Idempotency-Key': KEY }, onUploadProgress: progress })
    expect(data.bankTransfer.proof.name).toBe('r.pdf')
    const breadcrumb = logger.breadcrumb.mock.calls.find(
      (call) => call[1] === 'payments.submitTransfer',
    )
    expect(breadcrumb[2].payloadShape).not.toContain('r.pdf')
  })

  it('reads the checkout options, cancels an order and asks for the own receipt link', async () => {
    apiClient.get.mockResolvedValue(envelope({ channels: ['electronic'] }))
    apiClient.post.mockResolvedValue(envelope({}))
    expect((await paymentsService.checkoutOptions()).data.channels).toEqual(['electronic'])
    await paymentsService.cancel('p-1')
    await paymentsService.requestTransferProofDownload('p-1')
    expect(apiClient.get).toHaveBeenCalledWith('/payments/checkout-options')
    expect(apiClient.post.mock.calls.map((call) => call[0])).toEqual([
      '/payments/p-1/cancel',
      '/payments/p-1/bank-transfer/proof/download',
    ])
  })
})

describe('paymentChannelsService', () => {
  it('saves the channels with the Super Admin reason only when given', async () => {
    apiClient.put.mockResolvedValue(envelope({ channels: ['electronic', 'bank_transfer'] }))
    await paymentChannelsService.updateChannels(['electronic'])
    await paymentChannelsService.updateChannels(['bank_transfer'], 'Reason for the audit log.')
    expect(apiClient.put.mock.calls).toEqual([
      ['/settings/payment/channels', { channels: ['electronic'] }],
      [
        '/settings/payment/channels',
        { channels: ['bank_transfer'], reason: 'Reason for the audit log.' },
      ],
    ])
  })

  it('calls every account endpoint', async () => {
    apiClient.get.mockResolvedValue(envelope([ACCOUNT]))
    apiClient.post.mockResolvedValue(envelope(ACCOUNT))
    apiClient.patch.mockResolvedValue(envelope(ACCOUNT))
    apiClient.put.mockResolvedValue(envelope([ACCOUNT]))
    apiClient.delete.mockResolvedValue({ data: '' })
    const listed = await paymentChannelsService.listAccounts({ perPage: 100 })
    expect(listed.data[0].iban).toBe(ACCOUNT.iban)
    await paymentChannelsService.createAccount({ iban: ACCOUNT.iban })
    await paymentChannelsService.updateNote('acc-1', null)
    await paymentChannelsService.activate('acc-1')
    await paymentChannelsService.deactivate('acc-1', 'Reason for the audit log.')
    await paymentChannelsService.remove('acc-1')
    await paymentChannelsService.reorder(['acc-1'])
    expect(apiClient.post.mock.calls.map((call) => call[0])).toEqual([
      '/settings/payment/bank-accounts',
      '/settings/payment/bank-accounts/acc-1/activate',
      '/settings/payment/bank-accounts/acc-1/deactivate',
    ])
    expect(apiClient.patch).toHaveBeenCalledWith('/settings/payment/bank-accounts/acc-1', {
      payerNote: null,
    })
    expect(apiClient.delete).toHaveBeenCalledWith('/settings/payment/bank-accounts/acc-1', {
      data: {},
    })
    expect(apiClient.put).toHaveBeenCalledWith('/settings/payment/bank-accounts/order', {
      orderedIds: ['acc-1'],
    })
  })

  it('treats a blocked account as an expected answer', async () => {
    apiClient.post.mockRejectedValueOnce(rejection(409))
    await expect(paymentChannelsService.deactivate('acc-1')).rejects.toThrow('rejected')
    expect(logger.error).not.toHaveBeenCalled()
  })
})

describe('bankTransfersService', () => {
  it('lists, reads and decides transfers', async () => {
    apiClient.get.mockResolvedValue(
      envelope([{ id: 'p-1', entity: {} }], { counts: { underReview: 1 } }),
    )
    apiClient.post.mockResolvedValue(envelope({ id: 'p-1', entity: {} }))
    const listed = await bankTransfersService.list({ 'filter[status]': 'under-review' })
    expect(listed.meta.counts.underReview).toBe(1)
    await bankTransfersService.approve('p-1')
    await bankTransfersService.reject('p-1', {
      rejectionReason: 'other',
      note: 'Not in the statement.',
    })
    await bankTransfersService.reject(
      'p-1',
      { rejectionReason: 'unreadable', note: null },
      'Reason.',
    )
    expect(apiClient.post.mock.calls).toEqual([
      ['/finance/bank-transfers/p-1/approve', {}],
      [
        '/finance/bank-transfers/p-1/reject',
        { rejectionReason: 'other', note: 'Not in the statement.' },
      ],
      ['/finance/bank-transfers/p-1/reject', { rejectionReason: 'unreadable', reason: 'Reason.' }],
    ])
  })

  it('sends one reason for a bulk decision and maps the outcome', async () => {
    apiClient.post.mockResolvedValue(
      envelope({ decided: ['p-1'], skipped: [{ id: 'p-2', code: 'entity.deleted' }] }),
    )
    const { data } = await bankTransfersService.bulkReject(['p-1', 'p-2'], {
      rejectionReason: 'duplicate',
      note: null,
    })
    await bankTransfersService.bulkApprove(['p-1'], 'Reason for the audit log.')
    expect(data).toEqual({ decided: ['p-1'], skipped: [{ id: 'p-2', code: 'entity.deleted' }] })
    expect(apiClient.post.mock.calls).toEqual([
      [
        '/finance/bank-transfers/bulk-reject',
        { paymentIds: ['p-1', 'p-2'], rejectionReason: 'duplicate' },
      ],
      [
        '/finance/bank-transfers/bulk-approve',
        { paymentIds: ['p-1'], reason: 'Reason for the audit log.' },
      ],
    ])
  })
})
