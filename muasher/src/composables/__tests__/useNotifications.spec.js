import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { withSetup } from '@/test-utils/withSetup'
import { useNotifications } from '@/composables/useNotifications'
import { usePortalCountsStore } from '@/stores/portalCounts'
import { notificationsService } from '@/services/notificationsService'

vi.mock('@/services/notificationsService', () => ({
  notificationsService: {
    list: vi.fn(),
    unreadCount: vi.fn(),
    markRead: vi.fn(),
    markAllRead: vi.fn(),
  },
}))

const ID_A = '5e1f0c2a-0000-4000-8000-000000000001'
const ID_B = '5e1f0c2a-0000-4000-8000-000000000002'

function items() {
  return [
    { id: ID_A, isRead: false, title: { ar: 'أ', en: 'a' } },
    { id: ID_B, isRead: true, title: { ar: 'ب', en: 'b' } },
  ]
}

beforeEach(() => {
  vi.clearAllMocks()
  notificationsService.list.mockResolvedValue({ data: items(), meta: { unreadCount: 1 } })
  notificationsService.markRead.mockResolvedValue({ data: {} })
  notificationsService.markAllRead.mockResolvedValue({ data: { updated: 1 } })
})

describe('useNotifications', () => {
  it('loads the list and shares the unread count from the list meta', async () => {
    const { api } = withSetup(() => useNotifications({ perPage: 3 }))
    await flushPromises()
    expect(notificationsService.list).toHaveBeenCalledWith({ perPage: 3 })
    expect(api.status.value).toBe('success')
    expect(api.unreadCount.value).toBe(1)
    expect(usePortalCountsStore().unreadNotifications).toBe(1)
  })

  it('marks one item read once and decrements the shared count', async () => {
    const { api } = withSetup(() => useNotifications())
    await flushPromises()
    await api.markRead(ID_A)
    await api.markRead(ID_A)
    await api.markRead(ID_B)
    expect(notificationsService.markRead).toHaveBeenCalledTimes(1)
    expect(api.items.value[0].isRead).toBe(true)
    expect(api.unreadCount.value).toBe(0)
  })

  it('marks everything read', async () => {
    const { api } = withSetup(() => useNotifications())
    await flushPromises()
    await api.markAllRead()
    expect(api.items.value.every((item) => item.isRead)).toBe(true)
    expect(api.unreadCount.value).toBe(0)
  })

  it('reports the empty and error states', async () => {
    notificationsService.list.mockResolvedValueOnce({ data: [], meta: { unreadCount: 0 } })
    const empty = withSetup(() => useNotifications())
    await flushPromises()
    expect(empty.api.status.value).toBe('empty')
    notificationsService.list.mockRejectedValueOnce(new Error('boom'))
    const failing = withSetup(() => useNotifications())
    await flushPromises()
    expect(failing.api.status.value).toBe('error')
  })
})
