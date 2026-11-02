import { beforeEach, describe, expect, it, vi } from 'vitest'
import { paymentsService } from '@/services/paymentsService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const envelope = { data: { id: 'p-1' }, meta: {}, errors: null }
const KEY = '7b0f5a2e-1c3d-4e5f-8a9b-0c1d2e3f4a5b'

function rejection(status) {
  return Object.assign(new Error('rejected'), { response: { status } })
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('paymentsService', () => {
  it('opens a checkout with the purpose only and the attempt Idempotency-Key header', async () => {
    apiClient.post.mockResolvedValue({ data: envelope })
    await paymentsService.create('accredited', KEY)
    expect(apiClient.post).toHaveBeenCalledWith('/payments', { purpose: 'accredited' }, { headers: { 'Idempotency-Key': KEY } })
    const breadcrumb = logger.breadcrumb.mock.calls.find((call) => call[1] === 'payments.create')
    expect(breadcrumb[2]).toEqual({ entity: 'payment', payloadShape: ['purpose'] })
  })

  it('verifies with the gateway payment id only', async () => {
    apiClient.post.mockResolvedValue({ data: envelope })
    await paymentsService.verify('p-1', 'mock_pay_1')
    expect(apiClient.post).toHaveBeenCalledWith('/payments/p-1/verify', { providerPaymentId: 'mock_pay_1' })
  })

  it('reads the own list, a payment, its receipt and asks for a signed receipt link', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    apiClient.post.mockResolvedValue({ data: envelope })
    await paymentsService.listMine({ page: 2 })
    await paymentsService.get('p-1')
    await paymentsService.getReceipt('p-1')
    await paymentsService.requestReceiptDownload('p-1')
    expect(apiClient.get.mock.calls.map((call) => call[0])).toEqual(['/payments/mine', '/payments/p-1', '/payments/p-1/receipt'])
    expect(apiClient.post).toHaveBeenCalledWith('/payments/p-1/receipt/download')
  })

  it('treats domain answers as expected and reports anything else', async () => {
    apiClient.post.mockRejectedValueOnce(rejection(503))
    await expect(paymentsService.create('bag', KEY)).rejects.toThrow('rejected')
    apiClient.post.mockRejectedValueOnce(rejection(409))
    await expect(paymentsService.verify('p-1', 'x')).rejects.toThrow('rejected')
    expect(logger.error).not.toHaveBeenCalled()
    apiClient.post.mockRejectedValueOnce(rejection(500))
    await expect(paymentsService.verify('p-1', 'x')).rejects.toThrow('rejected')
    expect(logger.error).toHaveBeenCalledWith(expect.any(Error), expect.objectContaining({ action: 'payments.verify' }))
  })
})
