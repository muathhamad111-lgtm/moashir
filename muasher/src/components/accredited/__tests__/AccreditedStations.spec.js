import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AccreditedStations from '@/components/accredited/AccreditedStations.vue'
import { createTestGlobals } from '@/test-utils'

const STATIONS = [
  {
    id: 1,
    code: 'M1',
    name: { ar: 'القيادة', en: 'Leadership' },
    total: 3,
    complete: 3,
    state: 'complete',
  },
  {
    id: 2,
    code: 'M2',
    name: { ar: 'التخطيط', en: 'Planning' },
    total: 2,
    complete: 1,
    state: 'partial',
  },
  {
    id: 3,
    code: 'M3',
    name: { ar: 'الموارد', en: 'Resources' },
    total: 2,
    complete: 0,
    state: 'empty',
  },
]

describe('AccreditedStations', () => {
  it('shows each station with its isolated counts, state and the current step', async () => {
    const wrapper = mount(AccreditedStations, {
      props: { stations: STATIONS, activeId: 2 },
      global: createTestGlobals(),
    })
    const buttons = wrapper.findAll('.station-row__button')
    expect(buttons).toHaveLength(3)
    expect(buttons[1].attributes('aria-current')).toBe('step')
    expect(buttons[0].find('[data-icon="check"]').exists()).toBe(true)
    expect(buttons[1].find('.station-row__count').text()).toBe('1 / 2')
    expect(buttons[2].find('.station-row__sr').text()).toBe('لم يبدأ')
    expect(wrapper.find('.station-row__title').text()).toContain('3')
    await buttons[2].trigger('click')
    expect(wrapper.emitted('select')[0]).toEqual([3])
  })
})
