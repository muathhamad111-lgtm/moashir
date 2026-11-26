import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AssessorOverview from '../AssessorOverview.vue'
import { createTestGlobals } from '@/test-utils'
import { dashboardService } from '@/services/dashboardService'
import { toAssessorOverview } from '@/services/mappers/dashboardOverview'

vi.mock('@/services/dashboardService', () => ({
  dashboardService: { getAssessorOverview: vi.fn() },
}))

const LIVE = [
  { key: 'to_start', value: 1, format: 'count', link: null },
  { key: 'in_progress', value: 2, format: 'count', link: null },
  { key: 'awaiting_entity', value: 0, format: 'count', link: null },
  { key: 'overdue', value: 2, format: 'count', link: null },
  { key: 'due_within_week', value: 0, format: 'count', link: null },
]

const OVERVIEW = toAssessorOverview({
  period: { from: '2026-11-01', to: '2026-11-18', preset: 'month' },
  previousPeriod: { from: '2026-10-01', to: '2026-10-18', basis: 'elapsed_span' },
  cards: [
    { key: 'completed', value: 20, previous: 29, format: 'count' },
    { key: 'average_duration_days', value: '12.6', previous: '13.5', format: 'days' },
    { key: 'average_result_percent', value: '73.00', previous: '82.00', format: 'percent' },
    { key: 'completion_requests', value: 6, previous: 9, format: 'count' },
  ],
  trends: { bucket: 'day', buckets: ['2026-11-01', '2026-11-02'], series: { completed: [1, 2] } },
  paths: [
    { path: 'accredited', completed: 15 },
    { path: 'advanced', completed: 5 },
  ],
  levels: [
    { key: 'diamond', count: 1 },
    { key: 'gold', count: 3 },
    { key: 'silver', count: 7 },
    { key: 'bronze', count: 3 },
    { key: 'none', count: 1 },
  ],
  generatedAt: '2026-11-18T08:00:00+00:00',
})

async function mountOverview(locale = 'ar') {
  const globals = createTestGlobals({ locale })
  await globals.router.push('/dashboard')
  const wrapper = mount(AssessorOverview, {
    props: { liveCards: LIVE },
    global: { plugins: globals.plugins },
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  dashboardService.getAssessorOverview.mockResolvedValue({ data: OVERVIEW })
})

afterEach(() => {
  vi.clearAllMocks()
})

describe('AssessorOverview', () => {
  it('asks for this month and leads with the open reviews, wide, with their states', async () => {
    const wrapper = await mountOverview()
    expect(dashboardService.getAssessorOverview).toHaveBeenCalledWith({ period: 'month' })
    const open = wrapper.find('[data-card="open"]')
    expect(open.classes()).toContain('overview-cards__card--featured')
    expect(open.find('.overview-cards__value').text()).toBe('3')
    expect(open.findAll('.assessor-cards__state').map((state) => state.text())).toEqual([
      'بانتظار البدء1',
      'قيد التنفيذ2',
      'بانتظار الجهة0',
    ])
  })

  it('colours a live alert only while it counts something', async () => {
    const wrapper = await mountOverview()
    expect(wrapper.find('[data-card="overdue"]').classes()).toContain(
      'assessor-cards__alert--danger',
    )
    expect(wrapper.find('[data-card="due_within_week"]').classes()).not.toContain(
      'assessor-cards__alert--warning',
    )
  })

  it('shows the period tiles with their change, a shorter review time as good news', async () => {
    const wrapper = await mountOverview()
    expect(
      wrapper.findAll('.assessor-cards > li').map((card) => card.attributes('data-card')),
    ).toEqual([
      'open',
      'overdue',
      'due_within_week',
      'completed',
      'average_duration_days',
      'average_result_percent',
      'completion_requests',
    ])
    expect(wrapper.find('[data-card="average_duration_days"] .overview-delta').classes()).toContain(
      'overview-delta--inverse',
    )
    expect(wrapper.find('[data-card="completed"] .overview-delta').classes()).not.toContain(
      'overview-delta--inverse',
    )
  })

  it('draws the five charts of the board', async () => {
    const wrapper = await mountOverview('en')
    expect(
      wrapper.findAll('.overview-panel').map((panel) => panel.attributes('data-panel')),
    ).toEqual(['completed', 'open', 'paths', 'average', 'levels'])
    expect(wrapper.find('[data-panel="levels"]').text()).toContain('Below the levels')
  })
})
