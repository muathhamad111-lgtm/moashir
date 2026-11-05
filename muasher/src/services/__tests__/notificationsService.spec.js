import { describe, expect, it, vi, beforeEach } from 'vitest'
import { notificationsService } from '@/services/notificationsService'
import { apiClient } from '@/api/client'
import { logger } from '@/utils/logger'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), patch: vi.fn(), post: vi.fn(), put: vi.fn() },
}))
vi.mock('@/utils/logger', () => ({
  logger: { breadcrumb: vi.fn(), error: vi.fn(), info: vi.fn() },
}))

const ID = '5e1f0c2a-0000-4000-8000-000000000001'
const RAW = {
  id: ID,
  code: 'CNT-02',
  module: 'CNT',
  category: 'informational',
  title: { ar: 'ردّ', en: 'Reply' },
  body: { ar: 'نص', en: 'Body' },
  link: '/messages',
  path: null,
  pathLabel: null,
  isRead: false,
  readAt: null,
  archivedAt: null,
  createdAt: '2026-11-04T08:00:00Z',
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('notificationsService (Task-35 contract)', () => {
  it('lists notifications, maps them and keeps the meta', async () => {
    apiClient.get.mockResolvedValue({
      data: { data: [RAW], meta: { unreadCount: 1 }, errors: null },
    })
    const result = await notificationsService.list({ perPage: 3, 'filter[read]': 'false' })
    expect(apiClient.get).toHaveBeenCalledWith('/notifications', {
      params: { perPage: 3, 'filter[read]': 'false' },
    })
    expect(result.data).toEqual([RAW])
    expect(result.meta.unreadCount).toBe(1)
    expect(logger.breadcrumb).toHaveBeenCalledWith(
      'service',
      'notifications.list',
      expect.objectContaining({ entity: 'notification', paramsShape: ['perPage', 'filter[read]'] }),
    )
  })

  it('reads the unread count', async () => {
    apiClient.get.mockResolvedValue({ data: { data: { count: 2, byCategory: {} } } })
    const { data } = await notificationsService.unreadCount()
    expect(apiClient.get).toHaveBeenCalledWith('/notifications/unread-count')
    expect(data.count).toBe(2)
  })

  it('marks one read, archives one and marks all read', async () => {
    apiClient.patch.mockResolvedValue({ data: { data: { ...RAW, isRead: true } } })
    expect((await notificationsService.markRead(ID)).data.isRead).toBe(true)
    expect(apiClient.patch).toHaveBeenCalledWith(`/notifications/${ID}/read`)
    await notificationsService.archive(ID)
    expect(apiClient.patch).toHaveBeenCalledWith(`/notifications/${ID}/archive`)
    apiClient.post.mockResolvedValue({ data: { data: { updated: 3 } } })
    await notificationsService.markAllRead()
    expect(apiClient.post).toHaveBeenCalledWith('/notifications/read-all', {})
  })

  it('reads and updates the e-mail / SMS preferences', async () => {
    const row = {
      code: 'ACC-01',
      module: 'ACC',
      category: 'action_required',
      isMandatory: false,
      channels: [{ channel: 'email', enabled: true, configurable: true }],
    }
    apiClient.get.mockResolvedValue({ data: { data: [row] } })
    expect((await notificationsService.getPreferences()).data).toEqual([row])
    expect(apiClient.get).toHaveBeenCalledWith('/me/notification-preferences')
    apiClient.put.mockResolvedValue({ data: { data: [row] } })
    const change = { code: 'ACC-01', channel: 'email', enabled: false }
    await notificationsService.updatePreferences([change])
    expect(apiClient.put).toHaveBeenCalledWith('/me/notification-preferences', {
      preferences: [change],
    })
  })

  it('treats 422 on preferences as an expected rejection', async () => {
    const rejection = Object.assign(new Error('locked'), { response: { status: 422 } })
    apiClient.put.mockRejectedValue(rejection)
    await expect(notificationsService.updatePreferences([])).rejects.toBe(rejection)
    expect(logger.error).not.toHaveBeenCalled()
  })

  it('reports unexpected failures and rethrows', async () => {
    const failure = new Error('network')
    apiClient.get.mockRejectedValue(failure)
    await expect(notificationsService.unreadCount()).rejects.toBe(failure)
    expect(logger.error).toHaveBeenCalledWith(
      failure,
      expect.objectContaining({ action: 'notifications.unreadCount' }),
    )
  })
})
