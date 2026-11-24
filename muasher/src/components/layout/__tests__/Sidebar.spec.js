import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Sidebar from '@/components/layout/Sidebar.vue'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useBrandingStore } from '@/stores/branding'
import { DEFAULT_BACK_OFFICE_LOGOS } from '@/composables/useBackOfficeLogo'
import { DEFAULT_BRAND_COLORS } from '@/constants/branding'
import { deriveBrandScheme } from '@/utils/brandingContrast'
import { ROLES } from '@/constants/roles'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'
import { requestsService } from '@/services/requestsService'
import { dashboardService } from '@/services/dashboardService'

vi.mock('@/services/requestsService', () => ({
  requestsService: { listQueue: vi.fn(), listAssigned: vi.fn() },
}))
vi.mock('@/services/dashboardService', () => ({
  dashboardService: { getActionCenter: vi.fn() },
}))

const QUEUE_COUNTS = [
  { path: 'accredited', pathLabel: null, total: 7, awaitingAssignment: 3, overdue: 1 },
  { path: 'advanced', pathLabel: null, total: 4, awaitingAssignment: 0, overdue: 0 },
]

beforeEach(() => {
  localStorage.clear()
  requestsService.listQueue.mockResolvedValue({ data: [], meta: { pathCounts: QUEUE_COUNTS } })
  requestsService.listAssigned.mockResolvedValue({ data: [], meta: { pathCounts: [] } })
  dashboardService.getActionCenter.mockResolvedValue({ data: [], generatedAt: null })
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
      extra: { permissions: ['dashboard.view'] },
    })
    await wrapper.vm.$nextTick()
    const labels = wrapper.findAll('.sidebar__label').map((el) => el.text())
    expect(labels).toEqual(['لوحة المعلومات'])
  })

  it('filters items by the user roles (entity sees entity items only)', async () => {
    const { wrapper } = factory({ roles: [ROLES.ENTITY] })
    await wrapper.vm.$nextTick()
    const labels = wrapper.findAll('.sidebar__label').map((el) => el.text())
    expect(labels).toContain('التقييم الذاتي')
    expect(labels).toContain('الشهادات')
    expect(labels).not.toContain('طلبات التقييم')
    expect(labels).not.toContain('المستخدمون')
  })

  it('gives the System Administrator no bypass: only the modules its permissions allow', async () => {
    const { wrapper } = factory({ roles: [ROLES.SYSTEM_ADMIN] })
    await wrapper.vm.$nextTick()
    const labels = wrapper.findAll('.sidebar__label').map((el) => el.text())
    expect(wrapper.findAll('.sidebar__item')).toHaveLength(13)
    expect(labels).toContain('المستخدمون')
    expect(labels).not.toContain('إدارة الصلاحيات')
    expect(labels).toEqual(expect.arrayContaining(['المالية', 'صندوق الرسائل', 'المستخدمون']))
    expect(labels).not.toContain('الملخص المالي')
    expect(labels).not.toContain('الاستردادات')
    expect(labels).not.toContain('أحداث الدفع')
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

  it('lists the dashboard first without a caption, then groups items by module', async () => {
    const { wrapper } = factory({ roles: [ROLES.SYSTEM_ADMIN] })
    await wrapper.vm.$nextTick()
    const headless = wrapper
      .findAll('.sidebar__menu--headless .sidebar__label')
      .map((el) => el.text())
    expect(headless).toEqual(['لوحة المعلومات', 'الجهات', 'المالية', 'المستخدمون', 'صندوق الرسائل'])
    expect(wrapper.text()).not.toContain('الجهات المحذوفة')
    expect(wrapper.text()).not.toContain('طلبات تغيير البريد')
    expect(wrapper.text()).not.toContain('الإشعارات')
    const captions = wrapper.findAll('.sidebar__caption').map((el) => el.text())
    expect(captions).toEqual(['طلبات التقييم', 'إدارة الجائزة'])
    const group = wrapper.find('.sidebar__group')
    expect(group.attributes('role')).toBe('group')
    expect(group.attributes('aria-labelledby')).toBe('sidebar-group-assessment')
  })

  it('shows the union of modules for a multi-role user without any role switcher', async () => {
    const { wrapper } = factory({ roles: [ROLES.REQUEST_FOLLOWER, ROLES.CONTENT_OFFICER] })
    await wrapper.vm.$nextTick()
    const labels = wrapper.findAll('.sidebar__label').map((el) => el.text())
    expect(labels).toContain('التقييم المعتمد')
    expect(labels).toContain('التقييم المتقدم')
    expect(labels).toContain('صندوق الرسائل')
    expect(labels).not.toContain('إدارة المؤشرات')
    expect(labels).not.toContain('الهوية والألوان')
    expect(wrapper.find('[data-role-switcher]').exists()).toBe(false)
  })

  it('splits the requests queue per followed path with the awaiting count of each path', async () => {
    const { wrapper } = factory({ roles: [ROLES.REQUEST_FOLLOWER] })
    await flushPromises()
    const entries = wrapper.findAll('.sidebar__item--accredited, .sidebar__item--advanced')
    expect(entries.map((entry) => entry.find('.sidebar__label').text())).toEqual([
      'التقييم المعتمد',
      'التقييم المتقدم',
    ])
    expect(entries[0].attributes('href')).toBe('/admin/requests?path=accredited')
    expect(entries[0].find('.sidebar__count').text()).toBe('3')
    expect(entries[0].attributes('aria-label')).toBe('التقييم المعتمد — 3 بانتظار الإسناد')
    expect(entries[1].find('.sidebar__count').exists()).toBe(false)
  })

  it('never shows a path outside the follower scope', async () => {
    const { wrapper } = factory({
      roles: [ROLES.REQUEST_FOLLOWER],
      extra: { requestScope: { allPaths: false, paths: ['accredited'] } },
    })
    await flushPromises()
    const labels = wrapper.findAll('.sidebar__label').map((el) => el.text())
    expect(labels).toContain('التقييم المعتمد')
    expect(labels).not.toContain('التقييم المتقدم')
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
    expect(active[0].text()).toContain('التقييم المتقدم')
  })

  it('expands the group of the active route and folds the other groups', async () => {
    const { wrapper, globals } = factory({ roles: [ROLES.SYSTEM_ADMIN], extra: { id: 41 } })
    await globals.router.push('/admin/requests')
    await flushPromises()
    const toggle = wrapper.find('#sidebar-group-assessment')
    expect(toggle.attributes('aria-expanded')).toBe('true')
    expect(toggle.attributes('aria-controls')).toBe('sidebar-menu-assessment')
    expect(wrapper.find('#sidebar-menu-assessment').classes()).not.toContain(
      'sidebar__menu--closed',
    )
    expect(wrapper.find('#sidebar-group-award').attributes('aria-expanded')).toBe('false')
    expect(wrapper.find('#sidebar-menu-award').classes()).toContain('sidebar__menu--closed')
  })

  it('remembers the toggled groups per user and survives a broken stored value', async () => {
    localStorage.setItem('muasher.sidebar.groups.42', '{broken')
    const first = factory({ roles: [ROLES.SYSTEM_ADMIN], extra: { id: 42 } })
    await flushPromises()
    await first.wrapper.find('#sidebar-group-award').trigger('click')
    expect(first.wrapper.find('#sidebar-group-award').attributes('aria-expanded')).toBe('true')
    expect(JSON.parse(localStorage.getItem('muasher.sidebar.groups.42'))).toContain('award')
    const again = factory({ roles: [ROLES.SYSTEM_ADMIN], extra: { id: 42 } })
    await flushPromises()
    expect(again.wrapper.find('#sidebar-group-award').attributes('aria-expanded')).toBe('true')
    const other = factory({ roles: [ROLES.SYSTEM_ADMIN], extra: { id: 43 } })
    await flushPromises()
    expect(other.wrapper.find('#sidebar-group-award').attributes('aria-expanded')).toBe('false')
  })

  it('marks the item of the current route (and its child routes) as active with aria-current', async () => {
    const { wrapper, globals } = factory({ roles: [ROLES.SYSTEM_ADMIN] })
    await globals.router.push('/admin/permissions')
    await wrapper.vm.$nextTick()
    const active = wrapper.findAll('.sidebar__item.is-active')
    expect(active).toHaveLength(1)
    expect(active[0].text()).toBe('المستخدمون')
    expect(active[0].attributes('aria-current')).toBe('page')
    expect(wrapper.findAll('[aria-current="page"]')).toHaveLength(1)
  })

  it('keeps settings, branding and the audit log out of the sidebar', async () => {
    const { wrapper } = factory({ roles: [ROLES.SYSTEM_ADMIN] })
    await wrapper.vm.$nextTick()
    const labels = wrapper.findAll('.sidebar__label').map((el) => el.text())
    expect(labels).not.toContain('الهوية والألوان')
    expect(labels).not.toContain('الإعدادات')
    expect(labels).not.toContain('سجل التدقيق')
  })

  it('opens with the platform card: the logo for the dark gradient rail, linking to the dashboard', () => {
    const { wrapper } = factory()
    expect(wrapper.find('.identity-card__brand').attributes('href')).toBe('/dashboard')
    const logo = wrapper.find('.identity-card__logo')
    expect(logo.attributes('alt')).toBe('مؤشر، الانتقال إلى لوحة المعلومات')
    expect(logo.attributes('src')).toBe(DEFAULT_BACK_OFFICE_LOGOS.dark)
  })

  it('shows the uploaded logo for dark fills on a dark rail and the site logo on a light rail', async () => {
    const { wrapper, globals } = factory()
    const logos = { dark: 'blob:on-dark', site: 'blob:site' }
    const branding = useBrandingStore(globals.pinia)
    branding.apply({ logos })
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.identity-card__logo').attributes('src')).toBe('blob:on-dark')

    const lightRail = { top: '#f3f6f4', bottom: '#cfe7e4' }
    branding.apply({ logos, scheme: deriveBrandScheme(DEFAULT_BRAND_COLORS, {}, lightRail) })
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.identity-card__logo').attributes('src')).toBe('blob:site')
  })

  it('collapses via the ui store keeping accessible names', async () => {
    const { wrapper, globals } = factory({ roles: [ROLES.ENTITY] })
    const ui = useUiStore(globals.pinia)
    ui.toggleSidebar()
    await wrapper.vm.$nextTick()
    expect(wrapper.classes()).toContain('sidebar--collapsed')
    expect(wrapper.find('.sidebar__item').attributes('aria-label')).toBeTruthy()
    expect(wrapper.find('.identity-card').classes()).toContain('identity-card--collapsed')
  })

  it('is a nav landmark with an accessible label', () => {
    const { wrapper } = factory()
    expect(wrapper.element.tagName).toBe('NAV')
    expect(wrapper.attributes('aria-label')).toBe('التنقل الرئيسي')
  })

  it('has no separate executive dashboard; its old link lands on the dashboard', async () => {
    const { wrapper, globals } = factory({ roles: [ROLES.SYSTEM_ADMIN] })
    await globals.router.push('/dashboard/executive')
    await flushPromises()
    expect(globals.router.currentRoute.value.path).toBe('/dashboard')
    const current = wrapper.findAll('[aria-current="page"]').map((el) => el.text())
    expect(current).toEqual(['لوحة المعلومات'])
    expect(wrapper.findAll('.sidebar__label').map((el) => el.text())).not.toContain(
      'اللوحة التنفيذية',
    )
  })

  it('shows a short menu whole, under plain group headings that do not fold', async () => {
    const { wrapper } = factory({ roles: [ROLES.ASSESSOR] })
    await flushPromises()
    expect(wrapper.findAll('.sidebar__item').length).toBeLessThanOrEqual(10)
    const headings = wrapper.findAll('.sidebar__caption')
    expect(headings.length).toBeGreaterThan(0)
    headings.forEach((heading) => {
      expect(heading.element.tagName).toBe('P')
      expect(heading.attributes('aria-expanded')).toBeUndefined()
    })
    expect(wrapper.findAll('.sidebar__menu--closed')).toHaveLength(0)
    expect(wrapper.findAll('.sidebar__label').map((el) => el.text())).toContain(
      'حقيبة التميز السريع',
    )
  })

  it('counts the pending work on the menu item its link opens', async () => {
    dashboardService.getActionCenter.mockResolvedValue({
      data: [
        { key: 'answer_contact_messages', count: 2, link: '/admin/contact-messages?status=new' },
        { key: 'review_entity_email_changes', count: 1, link: '/admin/entities/email-changes' },
      ],
      generatedAt: null,
    })
    const { wrapper } = factory({ roles: [ROLES.SYSTEM_ADMIN] })
    await flushPromises()
    const item = (label) =>
      wrapper.findAll('.sidebar__item').find((el) => el.find('.sidebar__label').text() === label)
    expect(item('صندوق الرسائل').find('.sidebar__count').text()).toBe('2')
    expect(item('صندوق الرسائل').attributes('aria-label')).toBe('صندوق الرسائل — 2 بانتظار إجراء')
    expect(item('الجهات').find('.sidebar__count').text()).toBe('1')
    expect(item('المستخدمون').find('.sidebar__count').exists()).toBe(false)
  })
})
