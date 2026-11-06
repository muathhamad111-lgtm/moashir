import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Sidebar from '@/components/layout/Sidebar.vue'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useBrandingStore } from '@/stores/branding'
import { DEFAULT_BACK_OFFICE_LOGOS } from '@/composables/useBackOfficeLogo'
import { ROLES } from '@/constants/roles'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'
import { requestsService } from '@/services/requestsService'

vi.mock('@/services/requestsService', () => ({
  requestsService: { listQueue: vi.fn(), listAssigned: vi.fn() },
}))

const QUEUE_COUNTS = [
  { path: 'accredited', pathLabel: null, total: 7, awaitingAssignment: 3, overdue: 1 },
  { path: 'advanced', pathLabel: null, total: 4, awaitingAssignment: 0, overdue: 0 },
]

beforeEach(() => {
  localStorage.clear()
  requestsService.listQueue.mockResolvedValue({ data: [], meta: { pathCounts: QUEUE_COUNTS } })
  requestsService.listAssigned.mockResolvedValue({ data: [], meta: { pathCounts: [] } })
})

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
    const { wrapper } = factory({
      roles: [ROLES.SYSTEM_ADMIN],
      extra: { permissions: ['notifications.view'] },
    })
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
    expect(wrapper.findAll('.sidebar__item')).toHaveLength(27)
    expect(labels).toEqual(
      expect.arrayContaining([
        'الملخص المالي',
        'العمليات المالية',
        'الاستردادات',
        'أحداث الدفع',
        'رسائل تواصل معنا',
        'سجل التدقيق',
      ]),
    )
    expect(labels).not.toContain('التقييم الذاتي')
    expect(labels).not.toContain('لجنة المقيّمين')
  })

  it('shows the committee queue only with an active committee appointment', async () => {
    const plain = factory({ roles: [ROLES.ASSESSOR] })
    const appointed = factory({
      roles: [ROLES.ASSESSOR],
      extra: { committeeAppointments: [{ id: 7001, awardCycleId: 31, status: 'active' }] },
    })
    await plain.wrapper.vm.$nextTick()
    await appointed.wrapper.vm.$nextTick()
    expect(appointed.wrapper.findAll('.sidebar__item').length).toBe(
      plain.wrapper.findAll('.sidebar__item').length + 2,
    )
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
    expect(labels).toContain('طلبات المعتمد')
    expect(labels).toContain('طلبات المتقدم')
    expect(labels).toContain('إدارة المؤشرات')
    expect(labels).not.toContain('الهوية والألوان')
    expect(wrapper.find('[data-role-switcher]').exists()).toBe(false)
  })

  it('splits the requests queue per followed path with the awaiting count of each path', async () => {
    const { wrapper } = factory({ roles: [ROLES.REQUEST_FOLLOWER] })
    await flushPromises()
    const entries = wrapper.findAll('.sidebar__item--accredited, .sidebar__item--advanced')
    expect(entries.map((entry) => entry.find('.sidebar__label').text())).toEqual([
      'طلبات المعتمد',
      'طلبات المتقدم',
    ])
    expect(entries[0].attributes('href')).toBe('/admin/requests?path=accredited')
    expect(entries[0].find('.sidebar__count').text()).toBe('3')
    expect(entries[0].attributes('aria-label')).toBe('طلبات المعتمد — 3 بانتظار الإسناد')
    expect(entries[1].find('.sidebar__count').exists()).toBe(false)
  })

  it('never shows a path outside the follower scope', async () => {
    const { wrapper } = factory({
      roles: [ROLES.REQUEST_FOLLOWER],
      extra: { requestScope: { allPaths: false, paths: ['accredited'] } },
    })
    await flushPromises()
    const labels = wrapper.findAll('.sidebar__label').map((el) => el.text())
    expect(labels).toContain('طلبات المعتمد')
    expect(labels).not.toContain('طلبات المتقدم')
  })

  it('lists the assigned reviews per qualified path and marks the path of the route active', async () => {
    const { wrapper, globals } = factory({
      roles: [ROLES.ASSESSOR],
      extra: { assessorQualifications: ['accredited', 'advanced_initial'] },
    })
    await globals.router.push('/admin/requests/assigned?path=advanced')
    await flushPromises()
    const active = wrapper.findAll('.sidebar__item.is-active')
    expect(active).toHaveLength(1)
    expect(active[0].text()).toContain('مراجعات المتقدم')
  })

  it('expands the group of the active route and folds the other groups', async () => {
    const { wrapper, globals } = factory({ roles: [ROLES.SYSTEM_ADMIN], extra: { id: 41 } })
    await globals.router.push('/admin/settings/users')
    await flushPromises()
    const toggle = wrapper.find('#sidebar-group-administration')
    expect(toggle.attributes('aria-expanded')).toBe('true')
    expect(toggle.attributes('aria-controls')).toBe('sidebar-menu-administration')
    expect(wrapper.find('#sidebar-menu-administration').classes()).not.toContain(
      'sidebar__menu--closed',
    )
    expect(wrapper.find('#sidebar-group-finance').attributes('aria-expanded')).toBe('false')
    expect(wrapper.find('#sidebar-menu-finance').classes()).toContain('sidebar__menu--closed')
  })

  it('remembers the toggled groups per user and survives a broken stored value', async () => {
    localStorage.setItem('muasher.sidebar.groups.42', '{broken')
    const first = factory({ roles: [ROLES.SYSTEM_ADMIN], extra: { id: 42 } })
    await flushPromises()
    await first.wrapper.find('#sidebar-group-finance').trigger('click')
    expect(first.wrapper.find('#sidebar-group-finance').attributes('aria-expanded')).toBe('true')
    expect(JSON.parse(localStorage.getItem('muasher.sidebar.groups.42'))).toContain('finance')
    const again = factory({ roles: [ROLES.SYSTEM_ADMIN], extra: { id: 42 } })
    await flushPromises()
    expect(again.wrapper.find('#sidebar-group-finance').attributes('aria-expanded')).toBe('true')
    const other = factory({ roles: [ROLES.SYSTEM_ADMIN], extra: { id: 43 } })
    await flushPromises()
    expect(other.wrapper.find('#sidebar-group-finance').attributes('aria-expanded')).toBe('false')
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

  it('always shows the logo for a dark background, in dark mode and in light mode', async () => {
    const { wrapper, globals } = factory()
    const ui = useUiStore(globals.pinia)
    ui.setTheme('dark')
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.sidebar__logo').attributes('src')).toBe(DEFAULT_BACK_OFFICE_LOGOS.dark)
    ui.setTheme('light')
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.sidebar__logo').attributes('src')).toBe(DEFAULT_BACK_OFFICE_LOGOS.dark)
  })

  it.each(['dark', 'light'])(
    'prefers the custom dark-background logo in %s mode',
    async (theme) => {
      const { wrapper, globals } = factory()
      useUiStore(globals.pinia).setTheme(theme)
      useBrandingStore(globals.pinia).apply({
        accentToken: 'brand_default',
        logos: { dark: 'blob:on-dark', light: 'blob:on-light' },
      })
      await wrapper.vm.$nextTick()
      expect(wrapper.find('.sidebar__logo').attributes('src')).toBe('blob:on-dark')
    },
  )

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
