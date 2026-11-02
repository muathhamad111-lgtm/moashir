import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AppLayout from '@/components/layout/AppLayout.vue'
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

beforeEach(() => {
  vi.clearAllMocks()
  notificationsService.list.mockResolvedValue({ data: [], meta: {}, errors: null })
  notificationsService.unreadCount.mockResolvedValue({ data: { count: 0 }, meta: {}, errors: null })
})

describe('AppLayout', () => {
  it('composes TopNavbar + Sidebar around a main content slot', async () => {
    const wrapper = mount(AppLayout, {
      slots: { default: '<p class="page">محتوى الشاشة</p>' },
      global: createTestGlobals(),
    })
    await flushPromises()
    expect(wrapper.find('.navbar').exists()).toBe(true)
    expect(wrapper.find('.sidebar').exists()).toBe(true)
    expect(wrapper.find('main.app-layout__content .page').text()).toBe('محتوى الشاشة')
  })
})
