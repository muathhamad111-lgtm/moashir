import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ExecutiveOverview from '../ExecutiveOverview.vue'
import { createTestGlobals } from '@/test-utils'
import { dashboardService } from '@/services/dashboardService'
import { toExecutiveOverview } from '@/services/mappers/dashboardOverview'

vi.mock('@/services/dashboardService', () => ({ dashboardService: { getOverview: vi.fn() } }))
vi.mock('@/services/regionsService', () => ({
  regionsService: {
    listRegions: vi.fn().mockResolvedValue({
      data: [
        { id: 1, code: 'riyadh', name: { ar: 'منطقة الرياض', en: 'Riyadh Region' }, cities: [] },
      ],
    }),
  },
}))

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
  breakdowns: {
    requestsByStatus: [
      { key: 'awaiting_payment', count: 1 },
      { key: 'completed', count: 3 },
    ],
    paymentsByStatus: [{ key: 'paid', count: 4 }],
    entitiesByType: [{ key: 'waqf', count: 2 }],
    entitiesBySize: [{ key: 'small', count: 2 }],
    entitiesByRegion: [
      { key: 'riyadh', name: { ar: 'منطقة الرياض', en: 'Riyadh Region' }, count: 2 },
    ],
    assessorWorkload: [{ key: '12', name: 'Sara', open: 3, completed: 1 }],
    kpiTargets: [{ key: 'k1', name: { ar: 'مؤشر', en: 'Indicator' }, value: 40, target: 80 }],
    finance: {
      vatCollected: '150.00',
      refundsTotal: '0.00',
      receiptsIssued: 2,
      transfersAwaitingReview: 1,
      abandonedCarts: 0,
    },
    award: null,
    inbox: { received: 5, awaitingReply: 1, replied: 4, averageReplyHours: 6.5 },
    users: { staffActive: 4, entityAccounts: 9, mfaShare: 100, locked: 0, failedLogins: 2 },
    storage: {
      quotaBytes: 50 * 1024 ** 3,
      usedBytes: 45 * 1024 ** 3,
      addedInPeriodBytes: 1024 ** 3,
      warningPercent: 80,
      criticalPercent: 95,
      measuredAt: null,
    },
  },
  generatedAt: '2026-11-17T08:00:00+00:00',
})

async function mountAt(path = '/dashboard', locale = 'ar') {
  const globals = createTestGlobals({ locale })
  await globals.router.push(path)
  const wrapper = mount(ExecutiveOverview, {
    global: { plugins: globals.plugins, stubs: { teleport: true } },
    attachTo: document.body,
  })
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
  document.body.innerHTML = ''
})

async function openFilters(wrapper) {
  await wrapper.find('.board-head .filter-dialog__toggle').trigger('click')
  await flushPromises()
}

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
    expect(wrapper.find('[data-chip="period"]').text()).toBe('الفترة: شهري')
    await openFilters(wrapper)
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

  it('lays the cards out in rows that each end full, storage last', async () => {
    const { wrapper } = await mountAt('/dashboard', 'en')
    const rows = wrapper
      .findAll('.board-row')
      .map((row) =>
        row.findAll(':scope > .overview-panel').map((panel) => panel.attributes('data-panel')),
      )
    expect(rows).toEqual([
      ['periods'],
      ['geography', 'levels'],
      ['request-states', 'paths', 'payment-states'],
      ['governance', 'workload'],
      ['sectors', 'regions', 'profile', 'decades'],
      ['criteria', 'targets'],
      ['finance', 'award', 'inbox'],
      ['users', 'storage'],
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
  })

  it('hides the storage card without its data, giving the row to the users card', async () => {
    dashboardService.getOverview.mockResolvedValue({
      data: { ...OVERVIEW, breakdowns: { ...OVERVIEW.breakdowns, storage: null } },
    })
    const { wrapper } = await mountAt('/dashboard', 'en')
    const last = wrapper.findAll('.board-row').at(-1)
    expect(
      last.findAll(':scope > .overview-panel').map((panel) => panel.attributes('data-panel')),
    ).toEqual(['users'])
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
    await openFilters(wrapper)
    await wrapper.find('[data-period="quarter"]').trigger('click')
    await flushPromises()
    expect(globals.router.currentRoute.value.query).toEqual({ period: 'quarter' })
    expect(dashboardService.getOverview).toHaveBeenLastCalledWith({ period: 'quarter' })
  })

  it('chooses a month from the period popover and asks for it', async () => {
    const { wrapper, globals } = await mountAt('/dashboard', 'en')
    await openFilters(wrapper)
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
    await openFilters(wrapper)
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
    expect(wrapper.find('.board-head__error').text()).toBe('Too long.')
    expect(wrapper.find('.error-state').exists()).toBe(false)
    await openFilters(wrapper)
    expect(wrapper.find('.overview-period__error').text()).toBe('Too long.')
  })

  it('applies the board filters through the address, names them, and clears them', async () => {
    const { wrapper, globals } = await mountAt('/dashboard', 'en')
    await openFilters(wrapper)
    await wrapper.findComponent({ name: 'BoardFilters' }).vm.$nextTick()
    const selects = wrapper.findAllComponents({ name: 'AppSelect' })
    const named = (name) => selects.find((select) => select.props('name') === name)
    named('board-region').vm.$emit('update:modelValue', 'riyadh')
    named('board-size').vm.$emit('update:modelValue', 'small')
    await flushPromises()
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Apply')
      .trigger('click')
    await flushPromises()
    expect(globals.router.currentRoute.value.query).toEqual({ region: 'riyadh', size: 'small' })
    expect(dashboardService.getOverview).toHaveBeenLastCalledWith({
      period: 'month',
      region: 'riyadh',
      size: 'small',
    })
    expect(wrapper.find('[data-chip="region"]').text()).toBe('Region: Riyadh Region')
    expect(wrapper.find('[data-chip="size"]').text()).toBe('Entity size: Small')
    expect(wrapper.find('.filter-dialog__count').text()).toBe('2')
    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('Clear filters'))
      .trigger('click')
    await flushPromises()
    expect(globals.router.currentRoute.value.query).toEqual({})
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
