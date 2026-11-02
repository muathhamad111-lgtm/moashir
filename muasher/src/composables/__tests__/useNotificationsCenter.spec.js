import { describe, expect, it, vi, beforeEach } from 'vitest'
import { defineComponent, h, nextTick } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { useNotificationsCenter } from '@/composables/useNotificationsCenter'
import { useAuthStore } from '@/stores/auth'
import { notificationsService } from '@/services/notificationsService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/notificationsService', () => ({
  notificationsService: {
    list: vi.fn(),
    unreadCount: vi.fn(),
    markRead: vi.fn(),
    markAllRead: vi.fn(),
  },
}))

const rows = [
  { id: 1, type: 'action', read: false, recipientRole: 'entity', title: { ar: 'أ', en: 'a' }, link: '/x' },
  { id: 2, type: 'info', read: true, recipientRole: 'entity', title: { ar: 'ب', en: 'b' }, link: '/y' },
]

function meta(total = rows.length, lastPage = 1, page = 1) {
  return { pagination: { page, perPage: 8, total, lastPage } }
}

function harness(role = 'entity') {
  let api
  const Host = defineComponent({
    setup() {
      const auth = useAuthStore()
      auth.user = { name: 'Test', roles: role ? [role] : [] }
      api = useNotificationsCenter()
      return () => h('div')
    },
  })
  mount(Host, { global: createTestGlobals() })
  return api
}

beforeEach(() => {
  vi.clearAllMocks()
  notificationsService.list.mockResolvedValue({ data: rows, meta: meta(), errors: null })
  notificationsService.unreadCount.mockResolvedValue({ data: { count: 1 }, meta: {}, errors: null })
  notificationsService.markRead.mockResolvedValue({ data: { ...rows[0], read: true } })
  notificationsService.markAllRead.mockResolvedValue({ data: { updated: 1 } })
})

describe('useNotificationsCenter', () => {
  it('loads the list scoped to the current role and resolves success', async () => {
    const api = harness('entity')
    await flushPromises()
    expect(api.status.value).toBe('success')
    expect(api.items.value).toHaveLength(2)
    expect(api.unreadCount.value).toBe(1)
    expect(notificationsService.list).toHaveBeenCalledWith(
      expect.objectContaining({ 'filter[recipientRole]': 'entity', page: 1, perPage: 8 }),
    )
    expect(notificationsService.unreadCount).toHaveBeenCalledWith({
      'filter[recipientRole]': 'entity',
    })
  })

  it('maps the unread filter to filter[read]=false and a type filter to filter[type]', async () => {
    const api = harness('assessor')
    await flushPromises()

    api.setFilter('unread')
    await flushPromises()
    expect(notificationsService.list).toHaveBeenLastCalledWith(
      expect.objectContaining({ 'filter[read]': false }),
    )

    api.setFilter('alert')
    await flushPromises()
    expect(notificationsService.list).toHaveBeenLastCalledWith(
      expect.objectContaining({ 'filter[type]': 'alert' }),
    )
  })

  it('forwards the search term and resets to page 1', async () => {
    const api = harness('entity')
    await flushPromises()
    api.search.value = 'ACC-05'
    await flushPromises()
    expect(notificationsService.list).toHaveBeenLastCalledWith(
      expect.objectContaining({ search: 'ACC-05', page: 1 }),
    )
  })

  it('marks a single item read and decrements the unread count', async () => {
    const api = harness('entity')
    await flushPromises()
    await api.markRead(1)
    expect(notificationsService.markRead).toHaveBeenCalledWith(1)
    expect(api.items.value[0].read).toBe(true)
    expect(api.unreadCount.value).toBe(0)
  })

  it('marks all read passing the role payload and zeroes the count', async () => {
    const api = harness('entity')
    await flushPromises()
    await api.markAllRead()
    expect(notificationsService.markAllRead).toHaveBeenCalledWith({ recipientRole: 'entity' })
    expect(api.items.value.every((item) => item.read)).toBe(true)
    expect(api.unreadCount.value).toBe(0)
  })

  it('reports the empty state when no notifications match', async () => {
    notificationsService.list.mockResolvedValue({ data: [], meta: meta(0), errors: null })
    const api = harness('entity')
    await flushPromises()
    expect(api.status.value).toBe('empty')
  })

  it('reports the error state when the list service fails', async () => {
    notificationsService.list.mockRejectedValue(new Error('down'))
    const api = harness('entity')
    await flushPromises()
    expect(api.status.value).toBe('error')
  })

  it('exposes one filter tab per scope (all + unread + 4 types)', async () => {
    const api = harness('entity')
    await nextTick()
    expect(api.filterTabs.value.map((tab) => tab.value)).toEqual([
      'all',
      'unread',
      'action',
      'info',
      'alert',
      'error',
    ])
  })
})
