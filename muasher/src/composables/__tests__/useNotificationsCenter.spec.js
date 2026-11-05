import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { withSetup } from '@/test-utils/withSetup'
import { useNotificationsCenter } from '@/composables/useNotificationsCenter'
import { notificationsService } from '@/services/notificationsService'

vi.mock('@/services/notificationsService', () => ({
  notificationsService: {
    list: vi.fn(),
    markRead: vi.fn(),
    archive: vi.fn(),
    markAllRead: vi.fn(),
  },
}))

const TODAY = new Date().toISOString()

function notification(id, isRead) {
  return {
    id: `5e1f0c2a-0000-4000-8000-00000000000${id}`,
    isRead,
    category: 'informational',
    createdAt: TODAY,
  }
}

function envelope(items, total = items.length, unreadCount = 1) {
  return {
    data: items,
    meta: { unreadCount, pagination: { page: 1, perPage: 15, total, lastPage: 1 } },
  }
}

beforeEach(() => {
  vi.clearAllMocks()
  notificationsService.list.mockImplementation(() =>
    Promise.resolve(envelope([notification(1, false), notification(2, true)], 6, 1)),
  )
  notificationsService.markRead.mockResolvedValue({ data: {} })
  notificationsService.archive.mockResolvedValue({ data: {} })
  notificationsService.markAllRead.mockResolvedValue({ data: { updated: 1 } })
})

describe('useNotificationsCenter', () => {
  it('loads the all tab with counts and day groups', async () => {
    const { api } = withSetup(() => useNotificationsCenter())
    await flushPromises()
    expect(notificationsService.list).toHaveBeenCalledWith({ page: 1, perPage: 15 })
    expect(api.status.value).toBe('success')
    expect(api.tabs.value.map((tab) => [tab.value, tab.count])).toEqual([
      ['all', 6],
      ['unread', 1],
    ])
    expect(api.groups.value).toHaveLength(1)
    expect(api.groups.value[0].key).toBe('today')
  })

  it('filters unread with filter[read]=false and resets the page', async () => {
    const { api } = withSetup(() => useNotificationsCenter())
    await flushPromises()
    api.setTab('unread')
    await flushPromises()
    expect(notificationsService.list).toHaveBeenLastCalledWith({
      page: 1,
      perPage: 15,
      'filter[read]': 'false',
    })
  })

  it('opens the unread tab from the initial query', async () => {
    withSetup(() => useNotificationsCenter({ initialTab: 'unread' }))
    await flushPromises()
    expect(notificationsService.list.mock.calls[0][0]['filter[read]']).toBe('false')
  })

  it('marks an unread item read once', async () => {
    const { api } = withSetup(() => useNotificationsCenter())
    await flushPromises()
    const [unread, read] = api.items.value
    await api.markRead(unread)
    await api.markRead(read)
    expect(notificationsService.markRead).toHaveBeenCalledTimes(1)
    expect(unread.isRead).toBe(true)
    expect(api.unreadCount.value).toBe(0)
  })

  it('archives an item and removes it from the list', async () => {
    const { api } = withSetup(() => useNotificationsCenter())
    await flushPromises()
    await api.archive(api.items.value[0])
    expect(notificationsService.archive).toHaveBeenCalledWith(notification(1, false).id)
    expect(api.items.value).toHaveLength(1)
    expect(api.unreadCount.value).toBe(0)
  })

  it('marks all read only when something is unread', async () => {
    const { api } = withSetup(() => useNotificationsCenter())
    await flushPromises()
    await api.markAllRead()
    await api.markAllRead()
    expect(notificationsService.markAllRead).toHaveBeenCalledTimes(1)
    expect(api.items.value.every((item) => item.isRead)).toBe(true)
  })

  it('reports the empty and error states', async () => {
    notificationsService.list.mockImplementationOnce(() => Promise.resolve(envelope([], 0, 0)))
    const empty = withSetup(() => useNotificationsCenter())
    await flushPromises()
    expect(empty.api.status.value).toBe('empty')
    notificationsService.list.mockImplementationOnce(() => Promise.reject(new Error('boom')))
    const failing = withSetup(() => useNotificationsCenter())
    await flushPromises()
    expect(failing.api.status.value).toBe('error')
  })
})
