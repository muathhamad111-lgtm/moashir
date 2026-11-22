import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import StatsCards from '@/components/data-list/StatsCards.vue'
import { createTestGlobals } from '@/test-utils'

const stats = [
  { id: 1, icon: 'building', label: 'الجهات المسجلة', value: '148' },
  { id: 2, icon: 'award', label: 'الشهادات الصادرة', value: '96' },
]

describe('StatsCards', () => {
  it('renders one stats card per stat with value, label, and icon', () => {
    const wrapper = mount(StatsCards, { props: { stats } })
    const cards = wrapper.findAll('.stats-card')
    expect(cards).toHaveLength(2)
    expect(cards[0].find('.stats-card__value').text()).toBe('148')
    expect(cards[0].find('.stats-card__label').text()).toBe('الجهات المسجلة')
    expect(cards[0].find('.stats-card__icon').exists()).toBe(true)
  })

  it('renders skeleton cards while loading', () => {
    const wrapper = mount(StatsCards, { props: { stats: [], loading: true, skeletonCount: 3 } })
    expect(wrapper.findAll('.stats-card')).toHaveLength(3)
    expect(wrapper.findAll('.skeleton').length).toBeGreaterThan(0)
  })
})

// Admin review 2026-11-20 (R1): the request list's stat cards select its stage.
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
