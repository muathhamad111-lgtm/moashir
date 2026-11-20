import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import QueueStageChips from '@/components/requests/QueueStageChips.vue'
import StatsCards from '@/components/data-list/StatsCards.vue'

// Admin review 2026-11-20 (R1): one request list filtered by stage chips and by the stat cards.
describe('QueueStageChips', () => {
  it('lists every stage with its count and marks the chosen one', () => {
    const wrapper = mount(QueueStageChips, {
      props: {
        modelValue: 'preparing',
        counts: {
          all: 8,
          preparing: 1,
          awaiting_assignment: 1,
          in_progress: 3,
          overdue: 3,
          closed: 3,
        },
      },
      global: createTestGlobals(),
    })
    const chips = wrapper.findAll('button')
    expect(chips.map((chip) => chip.text())).toEqual([
      'الكل (8)',
      'قيد الإعداد (1)',
      'بانتظار الإسناد (1)',
      'قيد العمل (3)',
      'متأخرة (3)',
      'منتهية (3)',
    ])
    expect(chips[1].attributes('aria-pressed')).toBe('true')
    expect(wrapper.find('[role="group"]').attributes('aria-label')).toBe('مرحلة الطلب')
  })

  it('emits the stage of a clicked chip and shows bare labels before the counts arrive', async () => {
    const wrapper = mount(QueueStageChips, {
      props: { modelValue: 'all', counts: null },
      global: createTestGlobals({ locale: 'en' }),
    })
    expect(wrapper.find('[data-stage="closed"]').text()).toBe('Closed')
    await wrapper.find('[data-stage="overdue"]').trigger('click')
    expect(wrapper.emitted('update:modelValue')).toEqual([['overdue']])
  })
})

describe('StatsCards selectable', () => {
  const STATS = [
    { id: 'all', value: 8, label: 'Total' },
    { id: 'overdue', value: 3, label: 'Overdue' },
  ]

  it('turns each card into a toggle that selects its filter', async () => {
    const wrapper = mount(StatsCards, {
      props: { stats: STATS, selectable: true, active: 'overdue' },
      global: createTestGlobals({ locale: 'en' }),
    })
    const cards = wrapper.findAll('button.stats-card')
    expect(cards.map((card) => card.attributes('aria-pressed'))).toEqual(['false', 'true'])
    await cards[0].trigger('click')
    expect(wrapper.emitted('select')).toEqual([['all']])
  })

  it('keeps plain cards when not selectable', () => {
    const wrapper = mount(StatsCards, { props: { stats: STATS }, global: createTestGlobals() })
    expect(wrapper.findAll('button')).toHaveLength(0)
    expect(wrapper.findAll('.stats-card')).toHaveLength(2)
  })
})
