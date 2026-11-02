import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import StatsCards from '@/components/data-list/StatsCards.vue'

const stats = [
  { id: 1, icon: 'fa-solid fa-building', label: 'الجهات المسجلة', value: '148' },
  { id: 2, icon: 'fa-solid fa-award', label: 'الشهادات الصادرة', value: '96' },
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
