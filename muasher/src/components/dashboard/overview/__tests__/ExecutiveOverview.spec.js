import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ExecutiveOverview from '../ExecutiveOverview.vue'
import { createTestGlobals } from '@/test-utils'
import { dashboardService } from '@/services/dashboardService'
import { toExecutiveOverview } from '@/services/mappers/dashboardOverview'

vi.mock('@/services/dashboardService', () => ({ dashboardService: { getOverview: vi.fn() } }))

const OVERVIEW = toExecutiveOverview({
  period: { from: '2026-11-01', to: '2026-11-17', preset: 'month' },
  previousPeriod: { from: '2026-10-01', to: '2026-10-17', basis: 'elapsed_span' },
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
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(new Date('2026-11-17T09:00:00Z'))
  dashboardService.getOverview.mockResolvedValue({ data: OVERVIEW })
})

afterEach(() => {
  vi.useRealTimers()
  vi.clearAllMocks()
})

describe('ExecutiveOverview', () => {
  it('asks for this month by default and shows the period with its comparison', async () => {
    const { wrapper } = await mountAt()
    expect(dashboardService.getOverview).toHaveBeenCalledWith({ period: 'month' })
    expect(wrapper.find('.executive-overview__title').text()).toBe('نظرة عامة على المنصة')
    expect(wrapper.find('.executive-overview__period').text()).toContain('1 نوفمبر 2026')
    expect(wrapper.find('.executive-overview__compare').text()).toContain(
      'مقارنةً بالمدة نفسها من الفترة السابقة',
    )
    expect(wrapper.find('.executive-overview__compare').text()).toContain('17 أكتوبر 2026')
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

  it('chooses a month from the period popover and asks for it', async () => {
    const { wrapper, globals } = await mountAt('/dashboard', 'en')
    await wrapper.find('.overview-period__trigger').trigger('click')
    const september = wrapper
      .findAll('.period-grid__option')
      .find((option) => option.text() === 'September')
    await september.trigger('click')
    await flushPromises()
    expect(globals.router.currentRoute.value.query).toEqual({
      period: 'month',
      year: '2026',
      month: '9',
    })
    expect(dashboardService.getOverview).toHaveBeenLastCalledWith({
      period: 'month',
      year: '2026',
      month: '9',
    })
  })

  it('picks a custom range on one calendar: the first day, then the last', async () => {
    const { wrapper, globals } = await mountAt('/dashboard?period=custom')
    expect(dashboardService.getOverview).not.toHaveBeenCalled()
    await wrapper.find('[data-date="2026-11-10"]').trigger('click')
    expect(wrapper.find('.range-chooser__one-day').exists()).toBe(true)
    await wrapper.find('[data-date="2026-11-03"]').trigger('click')
    await flushPromises()
    expect(globals.router.currentRoute.value.query).toEqual({
      period: 'custom',
      from: '2026-11-03',
      to: '2026-11-10',
    })
    // ICU versions differ in the spaces around the range dash, so whitespace is ignored.
    expect(wrapper.find('.overview-period__current').text().replace(/\s/gu, '')).toBe(
      '3–10نوفمبر2026',
    )
  })

  it('shows the server refusal of a custom period without an error page', async () => {
    dashboardService.getOverview.mockRejectedValueOnce({
      normalized: { status: 422, message: 'Invalid', fieldErrors: { to: ['Too long.'] } },
    })
    const { wrapper } = await mountAt('/dashboard?period=custom&from=2026-01-01&to=2026-11-17')
    expect(dashboardService.getOverview).toHaveBeenCalledWith({
      period: 'custom',
      from: '2026-01-01',
      to: '2026-11-17',
    })
    expect(wrapper.find('.overview-period__error').text()).toBe('Too long.')
    expect(wrapper.find('.error-state').exists()).toBe(false)
  })

  it('prints the dashboard from the print button', async () => {
    const print = vi.spyOn(window, 'print').mockImplementation(() => {})
    const { wrapper } = await mountAt()
    const button = wrapper.find('.executive-overview__print')
    expect(button.attributes('aria-label')).toBe('طباعة اللوحة')
    expect(button.text()).toBe('')
    await button.trigger('click')
    expect(print).toHaveBeenCalledOnce()
    print.mockRestore()
  })
})
