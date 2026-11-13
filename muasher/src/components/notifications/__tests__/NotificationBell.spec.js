import { afterEach, describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import NotificationBell from '@/components/notifications/NotificationBell.vue'
import { notificationsService } from '@/services/notificationsService'
import { createTestGlobals } from '@/test-utils'
import { stubMatchMedia } from '@/test-utils/matchMedia'

vi.mock('@/services/notificationsService', () => ({
  notificationsService: {
    list: vi.fn(),
    unreadCount: vi.fn(),
    markRead: vi.fn(),
    markAllRead: vi.fn(),
  },
}))

const ID = '5e1f0c2a-0000-4000-8000-000000000005'
const item = {
  id: ID,
  code: 'ACC-05',
  module: 'ACC',
  category: 'action_required',
  title: { ar: 'إسناد طلب إليكم', en: 'Assigned to you' },
  body: { ar: 'تم إسناد طلب جديد.', en: 'A new request was assigned.' },
  link: '/admin/requests/9108/review',
  isRead: false,
  createdAt: '2026-07-06T11:20:00+03:00',
}

afterEach(() => vi.unstubAllGlobals())

beforeEach(() => {
  vi.clearAllMocks()
  notificationsService.list.mockResolvedValue({ data: [item], meta: { unreadCount: 3 } })
  notificationsService.markRead.mockResolvedValue({ data: { ...item, isRead: true } })
})

describe('NotificationBell (staff top bar)', () => {
  it('loads through the service and shows the unread count with an accessible name', async () => {
    const wrapper = mount(NotificationBell, { global: createTestGlobals() })
    await flushPromises()
    expect(notificationsService.list).toHaveBeenCalled()
    expect(wrapper.find('.notification-bell__count').text()).toBe('3')
    expect(wrapper.find('button').attributes('aria-label')).toContain('3')
    expect(wrapper.find('button svg').attributes('data-icon')).toBe('bell')
  })

  it('draws the dot-less bell without a badge when nothing is unread', async () => {
    notificationsService.list.mockResolvedValue({ data: [], meta: { unreadCount: 0 } })
    const wrapper = mount(NotificationBell, { global: createTestGlobals() })
    await flushPromises()
    expect(wrapper.find('button svg').attributes('data-icon')).toBe('bell')
    expect(wrapper.find('.notification-bell__count').exists()).toBe(false)
  })

  it('toggles the panel, marks an item read and follows its internal link', async () => {
    const globals = createTestGlobals()
    const push = vi.spyOn(globals.router, 'push')
    const wrapper = mount(NotificationBell, { global: globals })
    await flushPromises()
    expect(wrapper.find('.notification-panel').exists()).toBe(false)
    await wrapper.find('button').trigger('click')
    expect(wrapper.find('.notification-panel').exists()).toBe(true)
    await wrapper.find('.notification-item').trigger('click')
    await flushPromises()
    expect(notificationsService.markRead).toHaveBeenCalledWith(ID)
    expect(push).toHaveBeenCalledWith('/admin/requests/9108/review')
  })

  it('names the panel dialog by its heading on desktop', async () => {
    const wrapper = mount(NotificationBell, {
      global: createTestGlobals(),
      attachTo: document.body,
    })
    await flushPromises()
    await wrapper.find('button').trigger('click')
    await flushPromises()
    const dialog = wrapper.find('[role="dialog"]')
    expect(wrapper.find(`#${dialog.attributes('aria-labelledby')}`).text()).toBe('الإشعارات')
    expect(wrapper.find('button').attributes('aria-haspopup')).toBe('dialog')
    wrapper.unmount()
  })

  it('opens the panel as a full-width bottom sheet on a 390 px phone', async () => {
    stubMatchMedia({ width: 390 })
    const wrapper = mount(NotificationBell, {
      global: createTestGlobals(),
      attachTo: document.body,
    })
    await flushPromises()
    await wrapper.find('button').trigger('click')
    await flushPromises()
    expect(wrapper.find('.dropdown-sheet .notification-panel').exists()).toBe(true)
    expect(wrapper.find('.dropdown-sheet__title').text()).toBe('الإشعارات')
    expect(wrapper.find('.notification-panel__title').exists()).toBe(false)
    wrapper.unmount()
  })
})
