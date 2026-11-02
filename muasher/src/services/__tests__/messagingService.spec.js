import { describe, expect, it, vi, beforeEach } from 'vitest'
import { messagingService } from '@/services/messagingService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const envelope = { data: {}, meta: {}, errors: null }

beforeEach(() => {
  vi.clearAllMocks()
})

describe('messagingService', () => {
  it('lists segments and searches recipients with a params shape', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    await messagingService.listSegments()
    await messagingService.searchRecipients({ search: 'بر' })
    expect(apiClient.get).toHaveBeenCalledWith('/messaging/segments')
    expect(apiClient.get).toHaveBeenCalledWith('/messaging/recipients', { params: { search: 'بر' } })
  })

  it('sends an email campaign over a segment', async () => {
    apiClient.post.mockResolvedValue({ data: envelope })
    await messagingService.sendEmailCampaign({ subject: 'مرحبا', body: '...', segment: 'qualified' })
    expect(apiClient.post).toHaveBeenCalledWith('/messaging/email/send', {
      subject: 'مرحبا',
      body: '...',
      segment: 'qualified',
    })
  })

  it('lists abandoned carts and sends a marketing campaign', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    apiClient.post.mockResolvedValue({ data: envelope })
    await messagingService.listAbandonedCarts({ 'filter[purpose]': 'accredited' })
    await messagingService.sendCartCampaign({ channel: 'email', cartIds: ['cart-1'] })
    expect(apiClient.get).toHaveBeenCalledWith('/messaging/abandoned-carts', {
      params: { 'filter[purpose]': 'accredited' },
    })
    expect(apiClient.post).toHaveBeenCalledWith('/messaging/abandoned-carts/campaign', {
      channel: 'email',
      cartIds: ['cart-1'],
    })
  })

  it('reports failures to Sentry and rethrows', async () => {
    const failure = new Error('down')
    apiClient.post.mockRejectedValue(failure)
    await expect(messagingService.sendSms({ segment: 'all' })).rejects.toBe(failure)
    expect(logger.error).toHaveBeenCalledWith(
      failure,
      expect.objectContaining({ action: 'messaging.sendSms' }),
    )
  })
})
