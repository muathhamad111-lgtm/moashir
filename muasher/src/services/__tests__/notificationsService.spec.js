import { describe, expect, it, vi, beforeEach } from 'vitest'
import { notificationsService } from '@/services/notificationsService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), patch: vi.fn(), post: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const envelope = { data: [], meta: {}, errors: null }

beforeEach(() => {
  vi.clearAllMocks()
})

describe('notificationsService', () => {
  it('lists notifications through the axios client and unwraps the envelope', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    const result = await notificationsService.list({ perPage: 8 })
    expect(apiClient.get).toHaveBeenCalledWith('/notifications', { params: { perPage: 8 } })
    expect(result).toEqual(envelope)
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'notifications.list',
      expect.objectContaining({ entity: 'notification', paramsShape: ['perPage'] }),
    )
  })

  it('lists notifications with a role-scoped filter and reports the params shape', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    const params = {
      'filter[recipientRole]': 'entity',
      'filter[type]': 'action',
      page: 2,
      perPage: 8,
    }
    await notificationsService.list(params)
    expect(apiClient.get).toHaveBeenCalledWith('/notifications', { params })
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'notifications.list',
      expect.objectContaining({
        paramsShape: ['filter[recipientRole]', 'filter[type]', 'page', 'perPage'],
      }),
    )
  })

  it('reads the unread count with an optional role param and stays valid with no args', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    await notificationsService.unreadCount({ 'filter[recipientRole]': 'assessor' })
    expect(apiClient.get).toHaveBeenCalledWith('/notifications/unread-count', {
      params: { 'filter[recipientRole]': 'assessor' },
    })
    apiClient.get.mockClear()
    await notificationsService.unreadCount()
    expect(apiClient.get).toHaveBeenCalledWith('/notifications/unread-count', { params: {} })
  })

  it('loads the audit log for a single notification', async () => {
    apiClient.get.mockResolvedValue({ data: envelope })
    await notificationsService.getAudit(27)
    expect(apiClient.get).toHaveBeenCalledWith('/notifications/27/audit')
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'notifications.getAudit',
      expect.objectContaining({ entity: 'notification' }),
    )
  })

  it('marks a notification read via PATCH', async () => {
    apiClient.patch.mockResolvedValue({ data: { data: { id: 4, read: true } } })
    await notificationsService.markRead(4)
    expect(apiClient.patch).toHaveBeenCalledWith('/notifications/4/read')
  })

  it('marks all read with an optional role payload and stays backward-compatible', async () => {
    apiClient.post.mockResolvedValue({ data: { data: { updated: 3 } } })
    await notificationsService.markAllRead({ recipientRole: 'entity' })
    expect(apiClient.post).toHaveBeenCalledWith('/notifications/read-all', {
      recipientRole: 'entity',
    })
    apiClient.post.mockClear()
    await notificationsService.markAllRead()
    expect(apiClient.post).toHaveBeenCalledWith('/notifications/read-all', {})
  })

  it('reports failures to Sentry and rethrows (never swallows)', async () => {
    const failure = new Error('network')
    apiClient.get.mockRejectedValue(failure)
    await expect(notificationsService.unreadCount()).rejects.toBe(failure)
    expect(logger.error).toHaveBeenCalledWith(
      failure,
      expect.objectContaining({ action: 'notifications.unreadCount' }),
    )
  })
})
