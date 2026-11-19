import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ExecutiveOverview from '../ExecutiveOverview.vue'
import { createTestGlobals } from '@/test-utils'
import { dashboardService } from '@/services/dashboardService'
import { toExecutiveOverview } from '@/services/mappers/dashboardOverview'

vi.mock('@/services/dashboardService', () => ({ dashboardService: { getOverview: vi.fn() } }))

const OVERVIEW = toExecutiveOverview({
  period: { from: '2026-11-01', to: '2026-11-17', preset: 'month' },
  previousPeriod: { from: '2026-10-15', to: '2026-10-31' },
  cards: [
    { key: 'new_entities', value: 40, previous: 33, format: 'count' },
    { key: 'accredited_average_percent', value: '79.00', previous: '76.00', format: 'percent' },
    { key: 'payments_collected', value: '28750.00', previous: '29900.00', format: 'money' },
    { key: 'entities_active', value: 37, previous: null, format: 'count' },
  ],
  trends: {
    bucket: 'day',
    buckets: ['2026-11-01', '2026-11-02'],
    series: {
      entities: [1, 2],
      requests: [0, 1],
      completed: [1, 0],
      collected: ['0.00', '1150.00'],
    },
  },
  paths: [
    { path: 'self', submitted: null, completed: 4 },
    { path: 'accredited', submitted: 6, completed: 3 },
    { path: 'advanced', submitted: 2, completed: null },
  ],
  snapshot: [
    { key: 'entities_by_level', data: [{ key: 'gold', count: 2 }] },
    { key: 'entities_by_governance', data: [{ key: 'from_70_to_84', count: 5 }] },
    {
      key: 'lowest_criteria',
      data: [
        {
          code: 'M2',
          name: { ar: 'التخطيط', en: 'Planning' },
          averagePercent: '59.56',
          results: 4,
        },
        {
          code: 'M5',
          name: { ar: 'الابتكار', en: 'Innovation' },
          averagePercent: '60.00',
          results: 4,
        },
      ],
    },
  ],
  generatedAt: '2026-11-17T08:00:00+00:00',
})

async function mountAt(path = '/dashboard', locale = 'ar') {
  const globals = createTestGlobals({ locale })
  await globals.router.push(path)
  const wrapper = mount(ExecutiveOverview, { global: { plugins: globals.plugins } })
  await flushPromises()
  return { wrapper, globals }
}

beforeEach(() => {
  dashboardService.getOverview.mockResolvedValue({ data: OVERVIEW })
})

afterEach(() => {
  vi.clearAllMocks()
})

describe('ExecutiveOverview', () => {
  it('asks for this month by default and shows the period with its comparison', async () => {
    const { wrapper } = await mountAt()
    expect(dashboardService.getOverview).toHaveBeenCalledWith({ period: 'month' })
    expect(wrapper.find('.executive-overview__title').text()).toBe('نظرة عامة على المنصة')
    expect(wrapper.find('.executive-overview__period').text()).toContain('1 نوفمبر 2026')
    expect(wrapper.find('.executive-overview__compare').text()).toContain('مقارنةً بالفترة')
    expect(wrapper.find('[data-period="month"]').attributes('aria-pressed')).toBe('true')
  })

  it('compares each period card with the previous period, by points for a percent', async () => {
    const { wrapper } = await mountAt('/dashboard', 'en')
    const card = (key) => wrapper.find(`[data-card="${key}"]`)
    expect(card('new_entities').find('.overview-cards__label').text()).toBe('Entities registered')
    expect(card('new_entities').find('.overview-delta').attributes('data-direction')).toBe('up')
    expect(card('new_entities').find('.overview-delta bdi').text()).toBe('+21.2%')
    expect(card('accredited_average_percent').find('.overview-delta bdi').text()).toBe('+3 pts')
    expect(card('payments_collected').find('.overview-delta').attributes('data-direction')).toBe(
      'down',
    )
    expect(card('entities_active').find('.overview-delta').exists()).toBe(false)
    expect(card('entities_active').text()).toContain('Current state')
  })

  it('draws the activity, revenue, path, level and governance charts', async () => {
    const { wrapper } = await mountAt('/dashboard', 'en')
    const titles = wrapper.findAll('.overview-panel__title').map((title) => title.text())
    expect(titles).toEqual([
      'Platform activity',
      'Entities by level',
      'Amounts collected',
      'Requests by path',
      'Geographic distribution',
      'Accredited assessment average',
      'Entities by governance grade',
      'Entities by development sector',
      'Entities by founding decade',
      'Lowest-performing criteria',
    ])
    expect(
      wrapper.findAll('.overview-cards__card').map((card) => card.attributes('data-card')),
    ).toEqual([
      'payments_collected',
      'new_entities',
      'accredited_average_percent',
      'entities_active',
    ])
    expect(wrapper.find('.overview-cards__card--featured').attributes('data-card')).toBe(
      'payments_collected',
    )
    expect(wrapper.find('.chart--gauge svg').attributes('aria-label')).toContain('79')
  })

  it('ranks the lowest criteria as horizontal bars and states the result count once', async () => {
    const { wrapper } = await mountAt('/dashboard', 'en')
    const panel = wrapper.find('[data-panel="criteria"]')
    expect(panel.findAll('.ranked-bars__label').map((label) => label.text())).toEqual([
      'Planning',
      'Innovation',
    ])
    expect(panel.find('.ranked-bars__value').text()).toBe('59.56%')
    expect(panel.find('.overview-criteria__note').text()).toBe('Based on 4 results')
    expect(panel.find('.widget-criteria').exists()).toBe(false)
  })

  it('switches presets through the address and asks the server again', async () => {
    const { wrapper, globals } = await mountAt()
    await wrapper.find('[data-period="quarter"]').trigger('click')
    await flushPromises()
    expect(globals.router.currentRoute.value.query).toEqual({ period: 'quarter' })
    expect(dashboardService.getOverview).toHaveBeenLastCalledWith({ period: 'quarter' })
  })

  it('waits for both days of a custom period and shows the server refusal on its field', async () => {
    dashboardService.getOverview.mockRejectedValueOnce({
      normalized: { status: 422, message: 'Invalid', fieldErrors: { to: ['Too long.'] } },
    })
    const { wrapper } = await mountAt('/dashboard?period=custom&from=2020-01-01&to=2026-11-17')
    expect(dashboardService.getOverview).toHaveBeenCalledWith({
      period: 'custom',
      from: '2020-01-01',
      to: '2026-11-17',
    })
    expect(wrapper.find('.overview-period__custom').text()).toContain('Too long.')
    expect(wrapper.find('.error-state').exists()).toBe(false)

    vi.clearAllMocks()
    await mountAt('/dashboard?period=custom')
    expect(dashboardService.getOverview).not.toHaveBeenCalled()
  })

  it('prints the dashboard from the print button', async () => {
    const print = vi.spyOn(window, 'print').mockImplementation(() => {})
    const { wrapper } = await mountAt()
    await wrapper.find('.executive-overview__print').trigger('click')
    expect(print).toHaveBeenCalledOnce()
    print.mockRestore()
  })
})
