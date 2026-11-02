import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import Sidebar from '@/components/layout/Sidebar.vue'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useBrandingStore } from '@/stores/branding'
import { DEFAULT_BACK_OFFICE_LOGOS } from '@/composables/useBackOfficeLogo'
import { ROLES } from '@/constants/roles'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

function factory({ roles = [], locale = 'ar', extra = {} } = {}) {
  const globals = createTestGlobals({ locale })
  const wrapper = mount(Sidebar, { global: globals })
  const auth = useAuthStore(globals.pinia)
  auth.setUser(sessionUserFor(roles, extra))
  return { wrapper, globals }
}

describe('Sidebar', () => {
  it('shows nothing to a user without permissions (deny by default)', async () => {
    const { wrapper } = factory({ roles: [] })
    await wrapper.vm.$nextTick()
    expect(wrapper.findAll('.sidebar__item')).toHaveLength(0)
  })

  it('ignores role names and follows the permission keys only', async () => {
    const { wrapper } = factory({ roles: [ROLES.SYSTEM_ADMIN], extra: { permissions: ['notifications.view'] } })
    await wrapper.vm.$nextTick()
    const labels = wrapper.findAll('.sidebar__label').map((el) => el.text())
    expect(labels).toEqual(['الإشعارات'])
  })

  it('filters items by the user roles (entity sees entity items only)', async () => {
    const { wrapper } = factory({ roles: [ROLES.ENTITY] })
    await wrapper.vm.$nextTick()
    const labels = wrapper.findAll('.sidebar__label').map((el) => el.text())
    expect(labels).toContain('التقييم الذاتي')
    expect(labels).toContain('الشهادات')
    expect(labels).not.toContain('طلبات التقييم')
    expect(labels).not.toContain('المستخدمون والأدوار')
  })

  it('gives the System Administrator no bypass: only the modules its permissions allow', async () => {
    const { wrapper } = factory({ roles: [ROLES.SYSTEM_ADMIN] })
    await wrapper.vm.$nextTick()
    const labels = wrapper.findAll('.sidebar__label').map((el) => el.text())
    expect(wrapper.findAll('.sidebar__item')).toHaveLength(23)
    expect(labels).toEqual(expect.arrayContaining(['الملخص المالي', 'العمليات المالية', 'الاستردادات', 'أحداث الدفع']))
    expect(labels).not.toContain('التقييم الذاتي')
    expect(labels).not.toContain('لجنة المقيّمين')
  })

  it('shows the committee queue only with an active committee appointment', async () => {
    const plain = factory({ roles: [ROLES.ASSESSOR] })
    const appointed = factory({ roles: [ROLES.ASSESSOR], extra: { committeeAppointments: [{ cycleId: 'c-1' }] } })
    await plain.wrapper.vm.$nextTick()
    await appointed.wrapper.vm.$nextTick()
    expect(appointed.wrapper.findAll('.sidebar__item').length).toBe(plain.wrapper.findAll('.sidebar__item').length + 1)
  })

  it('groups items by module with a caption per group', async () => {
    const { wrapper } = factory({ roles: [ROLES.SYSTEM_ADMIN] })
    await wrapper.vm.$nextTick()
    const captions = wrapper.findAll('.sidebar__caption').map((el) => el.text())
    expect(captions).toEqual([
      'نظرة عامة',
      'إدارة الجهات',
      'الطلبات والتقييم',
      'الجائزة',
      'المحتوى والمعايير',
      'المالية',
      'إدارة النظام',
    ])
    const group = wrapper.find('.sidebar__group')
    expect(group.attributes('role')).toBe('group')
    expect(group.attributes('aria-labelledby')).toBe('sidebar-group-overview')
  })

  it('shows the union of modules for a multi-role user without any role switcher', async () => {
    const { wrapper } = factory({ roles: [ROLES.REQUEST_FOLLOWER, ROLES.CONTENT_OFFICER] })
    await wrapper.vm.$nextTick()
    const labels = wrapper.findAll('.sidebar__label').map((el) => el.text())
    expect(labels).toContain('طلبات التقييم')
    expect(labels).toContain('إدارة المؤشرات')
    expect(labels).not.toContain('الهوية والألوان')
    expect(wrapper.find('[data-role-switcher]').exists()).toBe(false)
  })

  it('marks the item of the current route (and its child routes) as active with aria-current', async () => {
    const { wrapper, globals } = factory({ roles: [ROLES.SYSTEM_ADMIN] })
    await globals.router.push('/admin/settings/general')
    await wrapper.vm.$nextTick()
    const active = wrapper.findAll('.sidebar__item.is-active')
    expect(active).toHaveLength(1)
    expect(active[0].text()).toBe('الإعدادات')
    expect(active[0].attributes('aria-current')).toBe('page')
    expect(wrapper.findAll('[aria-current="page"]')).toHaveLength(1)
  })

  it('shows the branding entry only to the System Administrator', async () => {
    const { wrapper } = factory({ roles: [ROLES.SYSTEM_ADMIN] })
    await wrapper.vm.$nextTick()
    expect(wrapper.findAll('.sidebar__label').map((el) => el.text())).toContain('الهوية والألوان')
  })

  it('renders the brand logo linking to the dashboard', () => {
    const { wrapper } = factory()
    expect(wrapper.find('.sidebar__brand').attributes('href')).toBe('/dashboard')
    expect(wrapper.find('.sidebar__logo').attributes('alt')).toBe('مؤشر، الانتقال إلى لوحة التحكم')
  })

  it('switches to the logo of the active background: dark mode then light mode', async () => {
    const { wrapper, globals } = factory()
    const ui = useUiStore(globals.pinia)
    ui.setTheme('dark')
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.sidebar__logo').attributes('src')).toBe(DEFAULT_BACK_OFFICE_LOGOS.dark)
    ui.setTheme('light')
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.sidebar__logo').attributes('src')).toBe(DEFAULT_BACK_OFFICE_LOGOS.light)
  })

  it('prefers the custom logo uploaded for the active background', async () => {
    const { wrapper, globals } = factory()
    useUiStore(globals.pinia).setTheme('light')
    useBrandingStore(globals.pinia).apply({ accentToken: 'brand_default', logos: { dark: null, light: 'blob:on-light' } })
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.sidebar__logo').attributes('src')).toBe('blob:on-light')
  })

  it('collapses via the ui store keeping accessible names', async () => {
    const { wrapper, globals } = factory({ roles: [ROLES.ENTITY] })
    const ui = useUiStore(globals.pinia)
    ui.toggleSidebar()
    await wrapper.vm.$nextTick()
    expect(wrapper.classes()).toContain('sidebar--collapsed')
    expect(wrapper.find('.sidebar__item').attributes('aria-label')).toBeTruthy()
  })

  it('is a nav landmark with an accessible label', () => {
    const { wrapper } = factory()
    expect(wrapper.element.tagName).toBe('NAV')
    expect(wrapper.attributes('aria-label')).toBe('التنقل الرئيسي')
  })
})
