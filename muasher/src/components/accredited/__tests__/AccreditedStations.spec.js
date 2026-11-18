import { describe, expect, it, vi } from 'vitest'
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

  it('keeps names as tooltips and accessible text in the compact row, never in the default one', () => {
    const compact = mount(AccreditedStations, {
      props: { stations: STATIONS, activeId: 1, compact: true },
      global: createTestGlobals(),
    })
    expect(compact.find('.station-row').classes()).toContain('station-row--compact')
    const buttons = compact.findAll('.station-row__button')
    expect(buttons.map((button) => button.attributes('title'))).toEqual([
      'القيادة',
      'التخطيط',
      'الموارد',
    ])
    expect(buttons[1].find('.station-row__name').text()).toBe('التخطيط')
    const regular = mount(AccreditedStations, {
      props: { stations: STATIONS },
      global: createTestGlobals(),
    })
    expect(regular.find('.station-row__button').attributes('title')).toBeUndefined()
  })

  it('shows the state inside the compact circle: check when complete, a partial fill otherwise', () => {
    const wrapper = mount(AccreditedStations, {
      props: {
        stations: [
          { ...STATIONS[0] },
          { ...STATIONS[1], scored: 2, scorable: 6 },
          { ...STATIONS[2] },
        ],
        activeId: 1,
        compact: true,
      },
      global: createTestGlobals(),
    })
    const markers = wrapper.findAll('.station-row__marker')
    // The current station is complete: it shows the check, not its number.
    expect(markers[0].text()).toBe('')
    expect(markers[1].attributes('style')).toContain('--station-progress: 33%')
    expect(markers[2].attributes('style')).toContain('--station-progress: 0%')
    expect(markers[2].text()).toBe('03')
  })

  it('celebrates a station once when it turns complete in the compact row', async () => {
    vi.useFakeTimers()
    const wrapper = mount(AccreditedStations, {
      props: { stations: STATIONS, activeId: 2, compact: true },
      global: createTestGlobals(),
    })
    expect(wrapper.find('.is-celebrating').exists()).toBe(false)
    await wrapper.setProps({
      stations: [STATIONS[0], { ...STATIONS[1], complete: 2, state: 'complete' }, STATIONS[2]],
    })
    const buttons = wrapper.findAll('.station-row__button')
    expect(buttons[1].classes()).toContain('is-celebrating')
    expect(buttons[0].classes()).not.toContain('is-celebrating')
    vi.advanceTimersByTime(1300)
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.is-celebrating').exists()).toBe(false)
    vi.useRealTimers()
  })

  it('colours the line after a complete station green and fills it up to the current one', () => {
    const wrapper = mount(AccreditedStations, {
      props: { stations: STATIONS, activeId: 3, compact: true },
      global: createTestGlobals(),
    })
    const items = wrapper.findAll('.station-row__item')
    expect(items[0].classes()).toEqual(
      expect.arrayContaining(['station-row__item--done', 'station-row__item--reached']),
    )
    expect(items[1].classes()).toContain('station-row__item--reached')
    expect(items[1].classes()).not.toContain('station-row__item--done')
    expect(items[2].classes()).not.toContain('station-row__item--reached')
    const regular = mount(AccreditedStations, {
      props: { stations: STATIONS, activeId: 3 },
      global: createTestGlobals(),
    })
    expect(regular.find('.station-row__item--reached').exists()).toBe(false)
  })
})
