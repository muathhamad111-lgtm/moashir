import { describe, expect, it, vi, beforeEach } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { useNotifications } from '@/composables/useNotifications'
import { notificationsService } from '@/services/notificationsService'

vi.mock('@/services/notificationsService', () => ({
  notificationsService: {
    list: vi.fn(),
    unreadCount: vi.fn(),
    markRead: vi.fn(),
    markAllRead: vi.fn(),
  },
}))

const items = [
  { id: 1, read: false, title: { ar: 'أ', en: 'a' } },
  { id: 2, read: true, title: { ar: 'ب', en: 'b' } },
]

function harness() {
  let api
  const Host = defineComponent({
    setup() {
      api = useNotifications()
      return () => h('div')
    },
  })
  mount(Host)
  return api
}

beforeEach(() => {
  vi.clearAllMocks()
  notificationsService.list.mockResolvedValue({ data: items, meta: {}, errors: null })
  notificationsService.unreadCount.mockResolvedValue({ data: { count: 1 }, meta: {}, errors: null })
  notificationsService.markRead.mockResolvedValue({ data: { ...items[0], read: true } })
  notificationsService.markAllRead.mockResolvedValue({ data: { updated: 1 } })
})

describe('useNotifications', () => {
  it('loads the list and unread count through the service', async () => {
    const api = harness()
    await flushPromises()
    expect(api.status.value).toBe('success')
    expect(api.items.value).toHaveLength(2)
    expect(api.unreadCount.value).toBe(1)
  })

  it('marks a single item read and decrements the unread count', async () => {
    const api = harness()
    await flushPromises()
    await api.markRead(1)
    expect(notificationsService.markRead).toHaveBeenCalledWith(1)
    expect(api.items.value[0].read).toBe(true)
    expect(api.unreadCount.value).toBe(0)
  })

  it('marks everything read', async () => {
    const api = harness()
    await flushPromises()
    await api.markAllRead()
    expect(api.items.value.every((item) => item.read)).toBe(true)
    expect(api.unreadCount.value).toBe(0)
  })

  it('reports the error state when the service fails', async () => {
    notificationsService.list.mockRejectedValue(new Error('down'))
    const api = harness()
    await flushPromises()
    expect(api.status.value).toBe('error')
  })
})
