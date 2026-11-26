import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BoardStorage from '../BoardStorage.vue'
import BoardPeriodBars from '../BoardPeriodBars.vue'
import BoardTargets from '../BoardTargets.vue'
import BoardFacts from '../BoardFacts.vue'
import { createTestGlobals } from '@/test-utils'

const GB = 1024 ** 3

function mountIn(component, props, locale = 'en') {
  return mount(component, { props, global: createTestGlobals({ locale }) })
}

function storage(usedGb, extra = {}) {
  return {
    quotaBytes: 50 * GB,
    usedBytes: usedGb * GB,
    addedInPeriodBytes: 1.5 * GB,
    warningPercent: 80,
    criticalPercent: 95,
    measuredAt: null,
    ...extra,
  }
}

describe('BoardStorage', () => {
  it('shows the used share of the quota with total, used, available and the period addition', () => {
    const wrapper = mountIn(BoardStorage, { storage: storage(20) })
    expect(wrapper.find('.board-storage').attributes('data-tone')).toBe('good')
    expect(wrapper.find('.board-storage__value bdi').text()).toBe('40%')
    const rows = wrapper.findAll('.board-storage__row').map((row) => row.text())
    expect(rows).toEqual(['Total50 GB', 'Used20 GB', 'Available30 GB', 'Added in the period1.5 GB'])
    expect(wrapper.find('.board-storage__alert').exists()).toBe(false)
  })

  it('warns past the warning threshold and alarms past the critical one', () => {
    const warning = mountIn(BoardStorage, { storage: storage(41) })
    expect(warning.find('.board-storage').attributes('data-tone')).toBe('warning')
    expect(warning.find('.board-storage__alert').text()).toBe(
      'Used storage is past the warning threshold.',
    )
    const critical = mountIn(BoardStorage, { storage: storage(48) })
    expect(critical.find('.board-storage').attributes('data-tone')).toBe('critical')
  })

  it('names when the disk was last measured, when it was', () => {
    const wrapper = mountIn(BoardStorage, {
      storage: storage(20, { measuredAt: '2026-11-26T00:00:00+03:00' }),
    })
    expect(wrapper.find('.board-storage__measured').text()).toContain('Last disk measurement')
  })
})

describe('BoardPeriodBars', () => {
  const props = {
    categories: ['Nov 1', 'Nov 2', 'Nov 3'],
    series: {
      requests: [1, 0, 2],
      entities: [3, 1, 1],
      completed: [0, 0, 1],
      collected: ['0.00', '1150.00', '0.00'],
    },
  }

  it('lists the latest period first and totals the chosen measure', () => {
    const wrapper = mountIn(BoardPeriodBars, props)
    expect(wrapper.findAll('.board-periods__row').map((row) => row.text())).toEqual([
      'Nov 32',
      'Nov 20',
      'Nov 11',
    ])
    expect(wrapper.find('.board-periods__total bdi').text()).toBe('3')
  })

  it('switches the measure, money included', async () => {
    const wrapper = mountIn(BoardPeriodBars, props)
    const button = (text) => wrapper.findAll('button').find((node) => node.text() === text)
    await button('Amounts collected').trigger('click')
    expect(button('Amounts collected').attributes('aria-pressed')).toBe('true')
    expect(wrapper.find('.board-periods__total bdi').text()).toContain('1150')
  })
})

describe('BoardTargets and BoardFacts', () => {
  it('shows each indicator against its target and caps the bar at full', () => {
    const wrapper = mountIn(BoardTargets, {
      caption: 'Targets',
      rows: [
        { key: 'a', label: 'Assessed', value: 40, target: 80 },
        { key: 'b', label: 'Beyond', value: 12, target: 10 },
      ],
    })
    expect(wrapper.findAll('.board-targets__figures').map((node) => node.text())).toEqual([
      '40 / 8050%',
      '12 / 10120%',
    ])
    expect(wrapper.findAll('.board-targets__fill')[1].attributes('style')).toContain('100%')
  })

  it('lists label and value pairs with their tone', () => {
    const wrapper = mountIn(BoardFacts, {
      caption: 'Inbox',
      rows: [{ key: 'awaiting', label: 'Awaiting reply', value: '3', tone: 'warning' }],
    })
    expect(wrapper.find('dt').text()).toBe('Awaiting reply')
    expect(wrapper.find('dd').classes()).toContain('board-facts__value--warning')
  })
})
