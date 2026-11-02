import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import NotificationBell from '@/components/notifications/NotificationBell.vue'
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

const item = {
  id: 5,
  code: 'ACC-05',
  type: 'action',
  title: { ar: 'إسناد طلب إليكم', en: 'Assigned to you' },
  body: { ar: 'تم إسناد طلب جديد.', en: 'A new request was assigned.' },
  link: '/admin/requests/9108/review',
  read: false,
  createdAt: '2026-07-06T11:20:00+03:00',
}

beforeEach(() => {
  vi.clearAllMocks()
  notificationsService.list.mockResolvedValue({ data: [item], meta: {}, errors: null })
  notificationsService.unreadCount.mockResolvedValue({ data: { count: 3 }, meta: {}, errors: null })
  notificationsService.markRead.mockResolvedValue({ data: { ...item, read: true } })
})

describe('NotificationBell', () => {
  it('loads through the service and shows the unread count with an accessible name', async () => {
    const wrapper = mount(NotificationBell, { global: createTestGlobals() })
    await flushPromises()
    expect(notificationsService.list).toHaveBeenCalled()
    expect(wrapper.find('.notification-bell__count').text()).toBe('3')
    expect(wrapper.find('button').attributes('aria-label')).toContain('3')
  })

  it('toggles the panel and marks an item read when opened', async () => {
    const wrapper = mount(NotificationBell, { global: createTestGlobals() })
    await flushPromises()
    expect(wrapper.find('.notification-panel').exists()).toBe(false)
    await wrapper.find('button').trigger('click')
    expect(wrapper.find('.notification-panel').exists()).toBe(true)
    await wrapper.find('.notification-item').trigger('click')
    await flushPromises()
    expect(notificationsService.markRead).toHaveBeenCalledWith(5)
  })
})
