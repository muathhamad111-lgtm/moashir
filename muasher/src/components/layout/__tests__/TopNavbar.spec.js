import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import TopNavbar from '@/components/layout/TopNavbar.vue'
import { useUiStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import { notificationsService } from '@/services/notificationsService'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'
import { useElementWidth } from '@/composables/useElementWidth'
import { NAVBAR_TOOLS_INLINE_MIN_WIDTH } from '@/constants/navigation'

vi.mock('@/composables/useElementWidth', async () => {
  const { ref } = await import('vue')
  return { useElementWidth: vi.fn(() => ref(Number.POSITIVE_INFINITY)) }
})

async function mountNarrow(roles) {
  const { ref } = await import('vue')
  useElementWidth.mockReturnValueOnce(ref(NAVBAR_TOOLS_INLINE_MIN_WIDTH - 1))
  return mountWithRoles(roles)
}

function menuLabels(wrapper) {
  return wrapper.findAll('[role="menuitem"]').map((item) => item.text())
}

async function mountWithRoles(roles) {
  const globals = createTestGlobals()
  useAuthStore(globals.pinia).setUser(sessionUserFor(roles, { name: 'Test User' }))
  const wrapper = mount(TopNavbar, { global: globals })
  await flushPromises()
  await wrapper.find('.navbar__user .navbar__avatar').trigger('click')
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
  it('renders no page title, plus the locale switcher, theme toggle, bell, and user menu', async () => {
    const globals = createTestGlobals()
    await globals.router.push('/dashboard')
    const wrapper = mount(TopNavbar, { global: globals })
    await flushPromises()
    expect(wrapper.find('.navbar__title').exists()).toBe(false)
    expect(wrapper.text()).toContain('English')
    expect(wrapper.find('.notification-bell').exists()).toBe(true)
    expect(wrapper.find('.navbar__user .dropdown-menu').exists()).toBe(true)
  })

  it('shows the entity-profile action + role badges for an entity session', async () => {
    const wrapper = await mountWithRoles(['entity'])
    expect(wrapper.find('.navbar__user-roles').exists()).toBe(true)
    const items = wrapper.findAll('[role="menuitem"]').map((b) => b.text())
    expect(items.some((label) => label.includes('الملف التعريفي'))).toBe(true)
  })

  it('hides the entity-profile action for a non-entity session but keeps logout', async () => {
    const wrapper = await mountWithRoles(['system_admin'])
    const items = wrapper.findAll('[role="menuitem"]').map((b) => b.text())
    expect(items.some((label) => label.includes('الملف التعريفي'))).toBe(false)
    expect(items.some((label) => label.includes('خروج'))).toBe(true)
    expect(wrapper.find('.navbar__user-roles').text()).toContain('مدير النظام')
  })

  it('offers "My account" to every signed-in user and shows every held role badge', async () => {
    const wrapper = await mountWithRoles(['request_follower', 'content_officer'])
    const items = wrapper.findAll('[role="menuitem"]').map((b) => b.text())
    expect(items[0]).toContain('حسابي')
    const badges = wrapper.findAll('.navbar__user-roles .badge').map((b) => b.text())
    expect(badges).toEqual(['متابع طلبات التقييم', 'مسؤول المحتوى'])
  })

  it('shows the generic person icon as the avatar of a staff user without a photo', async () => {
    const wrapper = await mountWithRoles(['system_admin'])
    const avatar = wrapper.find('.navbar__avatar')
    expect(avatar.text()).toBe('')
    expect(avatar.find('img').exists()).toBe(false)
    expect(avatar.find('[data-icon="user"]').exists()).toBe(true)
  })

  it('shows the uploaded photo of a staff user in the avatar', async () => {
    const globals = createTestGlobals()
    useAuthStore(globals.pinia).setUser(
      sessionUserFor(['assessor'], {
        name: 'Test User',
        avatar: { source: 'photo', url: '/api/v1/me/avatar?v=abc' },
      }),
    )
    const wrapper = mount(TopNavbar, { global: globals })
    await flushPromises()
    const image = wrapper.find('.navbar__avatar img')
    expect(image.attributes('src')).toBe('/api/v1/me/avatar?v=abc')
    expect(image.attributes('alt')).toBe('الصورة الشخصية لـ Test User')
    expect(image.classes()).not.toContain('account-avatar__image--logo')
  })

  it('shows the entity logo as the avatar of an entity account', async () => {
    const globals = createTestGlobals()
    useAuthStore(globals.pinia).setUser(
      sessionUserFor(['entity'], {
        name: 'Test User',
        entity: { id: 5001, name: { ar: 'جمعية البر', en: 'Al-Birr Society' } },
        avatar: { source: 'entity_logo', url: '/api/v1/me/avatar?v=logo' },
      }),
    )
    const wrapper = mount(TopNavbar, { global: globals })
    await flushPromises()
    const image = wrapper.find('.navbar__avatar img')
    expect(image.attributes('alt')).toBe('شعار جمعية البر')
    expect(image.classes()).toContain('account-avatar__image--logo')
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

  it('keeps settings, theme and language in the bar on a wide navbar', async () => {
    const wrapper = await mountWithRoles(['system_admin'])
    expect(wrapper.find('.navbar__settings').exists()).toBe(true)
    expect(wrapper.find('.navbar__locale').exists()).toBe(true)
    const labels = menuLabels(wrapper)
    expect(labels.some((label) => label.includes('الإعدادات'))).toBe(false)
    expect(labels.some((label) => label.includes('English'))).toBe(false)
  })

  it('moves settings, theme and language into the user menu on a narrow navbar', async () => {
    const wrapper = await mountNarrow(['system_admin'])
    expect(wrapper.find('.navbar__settings').exists()).toBe(false)
    expect(wrapper.find('.navbar__locale').exists()).toBe(false)
    expect(wrapper.find('.notification-bell').exists()).toBe(true)
    expect(menuLabels(wrapper)).toEqual([
      'حسابي',
      'الإعدادات',
      'التبديل إلى الوضع النهاري',
      'English',
      'تسجيل الخروج',
    ])
  })

  it('switches the theme from the narrow user menu', async () => {
    const wrapper = await mountNarrow(['entity'])
    const ui = useUiStore()
    const before = ui.theme
    const themeItem = wrapper
      .findAll('[role="menuitem"]')
      .find((item) => item.text().startsWith('التبديل إلى'))
    await themeItem.trigger('click')
    expect(ui.theme).not.toBe(before)
    expect(menuLabels(wrapper).some((label) => label.includes('الإعدادات'))).toBe(false)
  })
})
