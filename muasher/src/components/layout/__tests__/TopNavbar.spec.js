import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import TopNavbar from '@/components/layout/TopNavbar.vue'
import { useUiStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import { notificationsService } from '@/services/notificationsService'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

async function mountWithRoles(roles) {
  const globals = createTestGlobals()
  useAuthStore(globals.pinia).setUser(sessionUserFor(roles, { name: 'Test User' }))
  const wrapper = mount(TopNavbar, { global: globals })
  await flushPromises()
  await wrapper.find('.navbar__user .actions-dropdown button').trigger('click')
  await flushPromises()
  return wrapper
}

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

describe('TopNavbar', () => {
  it('renders the page title, locale switcher, theme toggle, bell, and user menu', async () => {
    const globals = createTestGlobals()
    await globals.router.push('/dashboard')
    const wrapper = mount(TopNavbar, { global: globals })
    await flushPromises()
    expect(wrapper.find('.navbar__title').text()).toBe('لوحة التحكم')
    expect(wrapper.text()).toContain('English')
    expect(wrapper.find('.notification-bell').exists()).toBe(true)
    expect(wrapper.find('.navbar__user .actions-dropdown').exists()).toBe(true)
  })

  it('shows the entity-profile action + role badges for an entity session', async () => {
    const wrapper = await mountWithRoles(['entity'])
    expect(wrapper.find('.navbar__user-roles').exists()).toBe(true)
    const items = wrapper.findAll('.actions-dropdown__item').map((b) => b.text())
    expect(items.some((label) => label.includes('الملف التعريفي'))).toBe(true)
  })

  it('hides the entity-profile action for a non-entity session but keeps logout', async () => {
    const wrapper = await mountWithRoles(['system_admin'])
    const items = wrapper.findAll('.actions-dropdown__item').map((b) => b.text())
    expect(items.some((label) => label.includes('الملف التعريفي'))).toBe(false)
    expect(items.some((label) => label.includes('خروج'))).toBe(true)
    expect(wrapper.find('.navbar__user-roles').text()).toContain('مدير النظام')
  })

  it('offers "My account" to every signed-in user and shows every held role badge', async () => {
    const wrapper = await mountWithRoles(['request_follower', 'content_officer'])
    const items = wrapper.findAll('.actions-dropdown__item').map((b) => b.text())
    expect(items[0]).toContain('حسابي')
    const badges = wrapper.findAll('.navbar__user-roles .badge').map((b) => b.text())
    expect(badges).toEqual(['متابع طلبات التقييم', 'مسؤول المحتوى'])
  })

  it('uses the user initial as the avatar trigger', async () => {
    const wrapper = await mountWithRoles(['system_admin'])
    expect(wrapper.find('.navbar__avatar').text()).toBe('T')
  })

  it('toggles the sidebar through the ui store with aria-expanded', async () => {
    const globals = createTestGlobals()
    const wrapper = mount(TopNavbar, { global: globals })
    await flushPromises()
    const ui = useUiStore(globals.pinia)
    const toggle = wrapper.find('[aria-label="طي/فتح القائمة الجانبية"]')
    expect(toggle.attributes('aria-expanded')).toBe('true')
    await toggle.trigger('click')
    expect(ui.sidebarCollapsed).toBe(true)
    expect(toggle.attributes('aria-expanded')).toBe('false')
  })

  it('shows the global search only to roles with a searchable scope', async () => {
    for (const [roles, visible] of [
      [['request_follower'], true],
      [['finance_officer'], true],
      [['content_officer'], false],
      [['entity'], false],
    ]) {
      const globals = createTestGlobals()
      useAuthStore(globals.pinia).setUser(sessionUserFor(roles, { name: 'Test User' }))
      const wrapper = mount(TopNavbar, { global: globals })
      await flushPromises()
      expect(wrapper.find('.global-search input[role="combobox"]').exists()).toBe(visible)
      wrapper.unmount()
    }
  })
})
