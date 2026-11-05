import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import StaffDashboard from '@/components/dashboard/StaffDashboard.vue'
import { dashboardService } from '@/services/dashboardService'
import { createTestGlobals } from '@/test-utils'
import { ROUTE_NAMES } from '@/constants/routeNames'

vi.mock('@/services/dashboardService', () => ({ dashboardService: { getBoard: vi.fn() } }))

const SUMMARY = {
  board: 'request_follower',
  landingBoard: 'request_follower',
  availableBoards: ['request_follower', 'assessor'],
  cards: [
    { key: 'awaiting_assignment', value: 3, format: 'count', link: '/admin/requests' },
    { key: 'completion_rate', value: '40.00', format: 'percent', link: null },
    { key: 'average_duration_days', value: '2.00', format: 'days', link: null },
    { key: 'payments_collected', value: '2300.00', format: 'money', link: null },
    { key: 'overdue', value: null, format: 'count', link: null },
  ],
  widgets: [
    { key: 'requests_by_path', data: [{ path: 'accredited', pathLabel: null, count: 9 }] },
    {
      key: 'pipeline',
      data: [{ path: 'accredited', pathLabel: null, status: 'submitted', count: 3 }],
    },
    {
      key: 'lowest_criteria',
      data: [
        {
          code: '4',
          name: { ar: 'إدارة الموارد', en: 'Resources' },
          averagePercent: '58.40',
          results: 6,
        },
      ],
    },
    {
      key: 'activity',
      data: [
        {
          assessmentId: 14,
          number: 'ACC-2026-000014',
          path: 'accredited',
          verb: 'submit',
          toStatus: 'submitted',
          at: '2026-11-04T07:20:00+00:00',
        },
      ],
    },
    {
      key: 'published_version',
      data: {
        versionNumber: 2,
        label: { ar: 'إصدار 2026', en: 'Version 2026' },
        publishedAt: '2026-09-01T09:00:00+00:00',
      },
    },
    { key: 'unknown_widget', data: [] },
  ],
  actionCenter: [
    {
      key: 'assign_requests',
      count: 3,
      severity: 'warning',
      link: '/admin/requests',
      dueAt: null,
      context: null,
    },
  ],
  generatedAt: '2026-11-05T06:00:00+00:00',
}

async function mountOn(query = {}, locale = 'ar') {
  const globals = createTestGlobals({ locale })
  await globals.router.push({ name: ROUTE_NAMES.APP_DASHBOARD, query })
  const wrapper = mount(StaffDashboard, { global: globals })
  await flushPromises()
  return { wrapper, router: globals.router }
}

beforeEach(() => {
  vi.clearAllMocks()
  dashboardService.getBoard.mockResolvedValue({ data: SUMMARY })
})

describe('StaffDashboard', () => {
  it('loads the landing board and offers the other held boards', async () => {
    const { wrapper } = await mountOn()
    expect(dashboardService.getBoard).toHaveBeenCalledWith(null)
    const links = wrapper.findAll('.dashboard-board-nav__link')
    expect(links.map((link) => link.text())).toEqual(['لوحة متابعة الطلبات', 'لوحة المقيّم'])
    expect(links[0].attributes('aria-current')).toBe('page')
    expect(links[1].attributes('href')).toContain('board=assessor')
  })

  it('renders every card label by key with its formatted value', async () => {
    const { wrapper } = await mountOn()
    const cards = wrapper.findAll('.dashboard-cards__item')
    expect(cards).toHaveLength(5)
    expect(cards[0].text()).toContain('بانتظار الإسناد')
    expect(cards[0].find('a').attributes('href')).toBe('/admin/requests')
    expect(cards[1].text()).toContain('40%')
    expect(cards[2].text()).toContain('يومان')
    expect(cards[3].find('.money-amount').exists()).toBe(true)
    expect(cards[4].text()).toContain('—')
  })

  it('renders each known widget with its renderer and skips unknown widgets', async () => {
    const { wrapper } = await mountOn({}, 'en')
    const titles = wrapper.findAll('.dashboard-widget__title').map((title) => title.text())
    expect(titles).toEqual([
      'Requests by path',
      'Requests by path and status',
      'Lowest-performing criteria',
      'Latest request updates',
      'Published criteria version',
    ])
    expect(wrapper.find('.widget-bars__row').text()).toContain('9')
    expect(wrapper.find('.widget-table__table caption').text()).toBe('Requests by path and status')
    expect(wrapper.find('.widget-criteria__row').text()).toContain('Resources')
    expect(wrapper.find('.widget-timeline__text').text()).toContain('Submitted')
    expect(wrapper.find('.widget-facts').text()).toContain('Version 2026')
    expect(wrapper.find('[data-widget="unknown_widget"]').exists()).toBe(false)
  })

  it('reloads when another board is chosen', async () => {
    const { router } = await mountOn()
    await router.push({ name: ROUTE_NAMES.APP_DASHBOARD, query: { board: 'assessor' } })
    await flushPromises()
    expect(dashboardService.getBoard).toHaveBeenLastCalledWith('assessor')
  })

  it('falls back to the landing board when a requested board is refused', async () => {
    dashboardService.getBoard
      .mockRejectedValueOnce({ normalized: { status: 403, message: 'Not held' } })
      .mockResolvedValue({ data: SUMMARY })
    const { router } = await mountOn({ board: 'finance_officer' })
    await flushPromises()
    expect(router.currentRoute.value.query.board).toBeUndefined()
    expect(dashboardService.getBoard).toHaveBeenLastCalledWith(null)
  })

  it('shows the error state with a retry', async () => {
    dashboardService.getBoard.mockRejectedValue({ normalized: { status: 500, message: 'Down' } })
    const { wrapper } = await mountOn()
    expect(wrapper.text()).toContain('Down')
    dashboardService.getBoard.mockResolvedValue({ data: SUMMARY })
    await wrapper.find('.error-state__retry button').trigger('click')
    await flushPromises()
    expect(wrapper.find('[data-board="request_follower"]').exists()).toBe(true)
  })
})
