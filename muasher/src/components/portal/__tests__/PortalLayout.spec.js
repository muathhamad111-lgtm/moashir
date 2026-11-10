import { afterEach, describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import PortalLayout from '@/components/layout/PortalLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { dashboardService } from '@/services/dashboardService'
import { notificationsService } from '@/services/notificationsService'
import { contactMessagesService } from '@/services/contactMessagesService'
import { certificatesService } from '@/services/certificatesService'
import { accreditedService } from '@/services/accreditedService'
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
vi.mock('@/services/certificatesService', () => ({
  certificatesService: { listCards: vi.fn() },
}))
vi.mock('@/services/accreditedService', () => ({
  accreditedService: { getCurrent: vi.fn() },
}))
vi.mock('@/config/levelAchieved', async (importOriginal) => ({
  ...(await importOriginal()),
  LEVEL_ACHIEVED_SINCE: Date.parse('2026-10-15T00:00:00+03:00'),
}))

const GOLD = {
  id: 'c-gold',
  number: 'CRT-2026-000031',
  level: 'gold',
  status: 'issued',
  percent: '82.00',
  issuedAt: '2026-10-30T08:00:00Z',
}

const ITEM = {
  key: 'complete_profile',
  count: 1,
  severity: 'warning',
  link: '/entity/profile',
  dueAt: null,
  context: { percent: 80 },
}

const mounted = []

async function at(path, { locale = 'ar' } = {}) {
  const globals = createTestGlobals({ locale })
  useAuthStore(globals.pinia).setUser(sessionUserFor(['entity'], { id: 'u-portal' }))
  await globals.router.push(path)
  const wrapper = mount(PortalLayout, {
    global: globals,
    slots: { default: '<p class="page-content">محتوى</p>' },
  })
  mounted.push(wrapper)
  await flushPromises()
  return wrapper
}

afterEach(() => {
  mounted.splice(0).forEach((wrapper) => wrapper.unmount())
})

beforeEach(() => {
  vi.clearAllMocks()
  dashboardService.getActionCenter.mockResolvedValue({ data: [ITEM], meta: {} })
  notificationsService.unreadCount.mockResolvedValue({ data: { count: 2 } })
  contactMessagesService.listMine.mockResolvedValue({ data: [], meta: { unreadReplies: 1 } })
  certificatesService.listCards.mockResolvedValue({ data: [] })
  accreditedService.getCurrent.mockResolvedValue({ data: { active: null } })
  localStorage.clear()
})

describe('PortalLayout', () => {
  it('renders the slim top bar, the strip, the page and the mobile tab bar on hub screens', async () => {
    const wrapper = await at('/dashboard')
    expect(wrapper.find('.portal-top-bar').exists()).toBe(true)
    expect(wrapper.find('.sidebar').exists()).toBe(false)
    expect(wrapper.find('.attention-strip').exists()).toBe(true)
    expect(wrapper.find('.page-content').exists()).toBe(true)
    expect(wrapper.find('.mobile-tab-bar').exists()).toBe(true)
    expect(wrapper.find('.side-nav').exists()).toBe(true)
    expect(wrapper.find('.side-nav-item[href="/messages"] .side-nav-item__count').text()).toBe('1')
    expect(wrapper.find('.portal-bell__dot').exists()).toBe(true)
    expect(wrapper.find('.portal-bell svg').attributes('data-icon')).toBe('bell')
  })

  it('shows «آخر تحديث» from the server time of the Action Center data', async () => {
    dashboardService.getActionCenter.mockResolvedValue({
      data: [ITEM],
      meta: {},
      generatedAt: '2026-11-05T08:00:00+00:00',
    })
    const wrapper = await at('/dashboard')
    const updated = wrapper.find('[data-testid="portal-updated"]')
    expect(updated.text()).toContain('آخر تحديث')
    expect(updated.find('bdi').text()).toContain('2026')
  })

  it('hides «آخر تحديث» when the server sends no time', async () => {
    const wrapper = await at('/dashboard')
    expect(wrapper.find('[data-testid="portal-updated"]').exists()).toBe(false)
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

  it('marks the active item in the side navigation and names the area in the top bar', async () => {
    const wrapper = await at('/payments')
    expect(wrapper.find('.side-nav-item.is-active').text()).toContain('الدفع والإيصالات')
    expect(wrapper.find('.side-nav-item.is-active').attributes('aria-current')).toBe('page')
    expect(wrapper.find('.portal-top-bar__area').text()).toBe('حساب الجهة')
  })

  it('collapses the side navigation and remembers the choice for the same user', async () => {
    const wrapper = await at('/dashboard')
    await wrapper.find('[data-testid="portal-nav-collapse"]').trigger('click')
    expect(wrapper.classes()).toContain('portal-layout--nav-collapsed')
    expect(wrapper.find('.side-nav-item__label').exists()).toBe(false)
    const again = await at('/dashboard')
    expect(again.classes()).toContain('portal-layout--nav-collapsed')
    await again.find('[data-testid="portal-nav-collapse"]').trigger('click')
    expect(again.classes()).not.toContain('portal-layout--nav-collapsed')
  })

  it('keeps working when the browser storage is unavailable', async () => {
    const original = localStorage.getItem.bind(localStorage)
    const spy = vi.spyOn(localStorage, 'getItem').mockImplementation((key) => {
      if (key.startsWith('muasher.portal')) throw new Error('blocked')
      return original(key)
    })
    try {
      const wrapper = await at('/dashboard')
      expect(wrapper.classes()).not.toContain('portal-layout--nav-collapsed')
    } finally {
      spy.mockRestore()
    }
  })

  it('lists the 3 live excellence tools and the 5 coming-soon teasers', async () => {
    const wrapper = await at('/dashboard')
    const toggle = wrapper.find('[data-testid="portal-tools-toggle"]')
    expect(toggle.attributes('aria-expanded')).toBe('false')
    await toggle.trigger('click')
    const tools = wrapper.findAll('.side-nav__list--tools .side-nav-item')
    expect(tools).toHaveLength(8)
    tools.slice(0, 3).forEach((tool) => expect(tool.find('.coming-soon-chip').exists()).toBe(false))
    tools.slice(3).forEach((tool) => expect(tool.find('.coming-soon-chip').text()).toBe('قريباً'))
    expect(tools[0].attributes('href')).toBe('/tools/maturity')
    expect(tools[1].attributes('href')).toBe('/tools/strengths-and-gaps')
  })

  it.each([
    [[], null, 'ابدأ التقييم المعتمد', 'none'],
    [[], 'under-review', 'تابع طلبك', 'progress'],
    [[{ ...GOLD, level: null, percent: '57.00' }], null, 'عرض شهادة المشاركة', 'participation'],
    [[GOLD], null, 'عرض الشهادة', 'gold'],
  ])('labels the level mini-card by tier', async (certificates, active, label, tier) => {
    certificatesService.listCards.mockResolvedValue({ data: certificates })
    accreditedService.getCurrent.mockResolvedValue({
      data: { active: active ? { id: 3, status: active } : null },
    })
    const wrapper = await at('/dashboard')
    const card = wrapper.find('[data-testid="level-mini-card"]')
    expect(card.find('.level-mini-card__action').text()).toBe(label)
    expect(card.find('.level-emblem').attributes('data-tier')).toBe(tier)
  })

  it('shows the level badge with the re-assessment chip on the account chip', async () => {
    certificatesService.listCards.mockResolvedValue({ data: [GOLD] })
    accreditedService.getCurrent.mockResolvedValue({
      data: { active: { id: 9, status: 'submitted' } },
    })
    const wrapper = await at('/dashboard')
    const badge = wrapper.find('.account-menu__level')
    expect(badge.find('.level-badge__label').text()).toBe('ذهبي')
    expect(badge.find('.level-badge__chip').text()).toBe('قيد إعادة التقييم')
  })

  it('shows no level-achieved dialog for a certificate issued before the cut-off', async () => {
    certificatesService.listCards.mockResolvedValue({
      data: [{ ...GOLD, issuedAt: '2026-09-05T08:00:00Z' }],
    })
    const wrapper = await at('/dashboard')
    expect(wrapper.find('.account-menu__level .level-badge__label').text()).toBe('ذهبي')
    expect(document.body.querySelector('.modal')).toBeNull()
  })

  it('opens the level-achieved dialog once per new certificate', async () => {
    certificatesService.listCards.mockResolvedValue({ data: [GOLD] })
    await at('/dashboard')
    const dialog = document.body.querySelector('.modal [role="dialog"]')
    expect(dialog.textContent).toContain('مبارك! حصلت جهتك على المستوى الذهبي')
    expect(document.activeElement.textContent.trim()).toBe('عرض الشهادة')
    document.body.querySelector('.btn--ghost').click()
    await flushPromises()
    expect(document.body.querySelector('.modal')).toBeNull()
    mounted.splice(0).forEach((item) => item.unmount())
    await at('/dashboard')
    expect(document.body.querySelector('.modal')).toBeNull()
    mounted.splice(0).forEach((item) => item.unmount())
    certificatesService.listCards.mockResolvedValue({
      data: [{ ...GOLD, id: 'c-diamond', level: 'diamond' }],
    })
    await at('/dashboard')
    expect(document.body.querySelector('.modal [role="dialog"]').textContent).toContain(
      'المستوى الماسي',
    )
  })
})
