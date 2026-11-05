import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import PortalLayout from '@/components/layout/PortalLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { dashboardService } from '@/services/dashboardService'
import { notificationsService } from '@/services/notificationsService'
import { contactMessagesService } from '@/services/contactMessagesService'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/dashboardService', () => ({
  dashboardService: { getActionCenter: vi.fn() },
}))
vi.mock('@/services/notificationsService', () => ({
  notificationsService: { unreadCount: vi.fn() },
}))
vi.mock('@/services/contactMessagesService', () => ({
  contactMessagesService: { listMine: vi.fn() },
}))

const ITEM = {
  key: 'complete_profile',
  count: 1,
  severity: 'warning',
  link: '/entity/profile',
  dueAt: null,
  context: { percent: 80 },
}

async function at(path) {
  const globals = createTestGlobals()
  useAuthStore(globals.pinia).setUser(sessionUserFor(['entity']))
  await globals.router.push(path)
  const wrapper = mount(PortalLayout, {
    global: globals,
    slots: { default: '<p class="page-content">محتوى</p>' },
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  vi.clearAllMocks()
  dashboardService.getActionCenter.mockResolvedValue({ data: [ITEM], meta: {} })
  notificationsService.unreadCount.mockResolvedValue({ data: { count: 2 } })
  contactMessagesService.listMine.mockResolvedValue({ data: [], meta: { unreadReplies: 1 } })
})

describe('PortalLayout', () => {
  it('renders the slim top bar, the strip, the page and the mobile tab bar on hub screens', async () => {
    const wrapper = await at('/dashboard')
    expect(wrapper.find('.portal-top-bar').exists()).toBe(true)
    expect(wrapper.find('.sidebar').exists()).toBe(false)
    expect(wrapper.find('.attention-strip').exists()).toBe(true)
    expect(wrapper.find('.page-content').exists()).toBe(true)
    expect(wrapper.find('.mobile-tab-bar').exists()).toBe(true)
    expect(wrapper.find('.portal-nav__badge').text()).toContain('1')
    expect(wrapper.find('.portal-bell__dot').exists()).toBe(true)
    expect(wrapper.find('.portal-bell svg').attributes('data-icon')).toBe('bell')
  })

  it('draws the bell without any dot when nothing is unread', async () => {
    notificationsService.unreadCount.mockResolvedValue({ data: { count: 0 } })
    const wrapper = await at('/dashboard')
    expect(wrapper.find('.portal-bell svg').attributes('data-icon')).toBe('bell')
    expect(wrapper.find('.portal-bell__dot').exists()).toBe(false)
  })

  it('hides the strip and the tab bar on task screens', async () => {
    const wrapper = await at('/assessment/accredited/form/14')
    expect(wrapper.classes()).toContain('portal-layout--task')
    expect(wrapper.find('.attention-strip').exists()).toBe(false)
    expect(wrapper.find('.mobile-tab-bar').exists()).toBe(false)
  })

  it('keeps the strip on desktop but drops the tab bar on the mobile-only task screen', async () => {
    const wrapper = await at('/messages/7c2f4e1a-52b0-4c8e-9a31-0000000000a1')
    expect(wrapper.classes()).toContain('portal-layout--mobile-task')
    expect(wrapper.find('.attention-strip').exists()).toBe(true)
    expect(wrapper.find('.mobile-tab-bar').exists()).toBe(false)
  })

  it('marks the active area in the top bar', async () => {
    const wrapper = await at('/payments')
    expect(wrapper.find('.portal-nav__link.is-active').text()).toContain('مدفوعاتي')
  })
})
