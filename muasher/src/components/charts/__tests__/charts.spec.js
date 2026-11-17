import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestGlobals } from '@/test-utils'
import TrendLineChart from '../TrendLineChart.vue'
import ColumnChart from '../ColumnChart.vue'
import DonutChart from '../DonutChart.vue'
import GaugeChart from '../GaugeChart.vue'
import SegmentedBar from '../SegmentedBar.vue'
import ProgressList from '../ProgressList.vue'

const format = (value) => (value === null || value === undefined ? '—' : String(value))
const SERIES = [
  { key: 'entities', label: 'Entities', values: [1, 3, 2], color: 'var(--chart-series-1)' },
  { key: 'requests', label: 'Requests', values: [0, 2, 4], color: 'var(--chart-series-2)' },
]

function line(props = {}) {
  return mount(TrendLineChart, {
    props: {
      categories: ['Nov 1', 'Nov 2', 'Nov 3'],
      series: SERIES,
      caption: 'Platform activity',
      categoryLabel: 'Date',
      formatValue: format,
      ...props,
    },
  })
}

describe('TrendLineChart', () => {
  it('draws one 2px line per series with a legend and a data table for screen readers', () => {
    const wrapper = line()
    expect(wrapper.findAll('.chart__line')).toHaveLength(2)
    expect(wrapper.findAll('.chart-legend__item').map((item) => item.text())).toEqual([
      'Entities',
      'Requests',
    ])
    const rows = wrapper.findAll('.chart-table tbody tr').map((row) => row.text())
    expect(rows).toEqual(['Nov 110', 'Nov 232', 'Nov 324'])
    expect(wrapper.find('.chart-table thead').text()).toBe('DateEntitiesRequests')
  })

  it('starts with the chosen series, adds the others from the legend and keeps one shown', async () => {
    const wrapper = line({ initialShown: ['entities'] })
    expect(wrapper.findAll('.chart__line')).toHaveLength(1)
    expect(wrapper.findAll('.chart__area')).toHaveLength(1)
    const toggle = (key) => wrapper.find(`[data-series="${key}"]`)
    expect(toggle('requests').attributes('aria-pressed')).toBe('false')
    await toggle('requests').trigger('click')
    expect(wrapper.findAll('.chart__line')).toHaveLength(2)
    expect(wrapper.findAll('.chart__area')).toHaveLength(0)
    await toggle('requests').trigger('click')
    await toggle('entities').trigger('click')
    expect(toggle('entities').attributes('aria-pressed')).toBe('true')
    expect(wrapper.findAll('.chart__line')).toHaveLength(1)
    expect(wrapper.find('.chart__line').attributes('d')).toMatch(/^M[\d.]+,[\d.]+ C/)
  })

  it('shows no legend box for a single series', () => {
    expect(
      line({ series: [SERIES[0]] })
        .find('.chart-legend')
        .exists(),
    ).toBe(false)
  })

  it('follows the keyboard across categories with a tooltip, and Escape clears it', async () => {
    const wrapper = line()
    const plot = wrapper.find('.chart__plot')
    expect(plot.attributes('tabindex')).toBe('0')
    expect(plot.attributes('aria-label')).toBe('Platform activity')
    await plot.trigger('keydown', { key: 'ArrowRight' })
    expect(wrapper.find('.chart-tooltip__title').text()).toBe('Nov 1')
    await plot.trigger('keydown', { key: 'ArrowRight' })
    expect(wrapper.find('.chart-tooltip__title').text()).toBe('Nov 2')
    expect(wrapper.findAll('.chart-tooltip__row dd').map((cell) => cell.text())).toEqual(['3', '2'])
    expect(wrapper.findAll('.chart__dot')).toHaveLength(2)
    await plot.trigger('keydown', { key: 'Escape' })
    expect(wrapper.find('.chart-tooltip').exists()).toBe(false)
  })
})

describe('ColumnChart', () => {
  it('draws a column per series and category, nothing for a zero, and labels every path', () => {
    const wrapper = mount(ColumnChart, {
      props: {
        categories: ['Self', 'Accredited', 'Award'],
        series: SERIES,
        caption: 'Requests by path',
        categoryLabel: 'Path',
        formatValue: format,
        labelAll: true,
      },
    })
    const bars = wrapper.findAll('.chart__bar')
    expect(bars).toHaveLength(6)
    expect(bars.filter((bar) => bar.attributes('d') === '')).toHaveLength(1)
    const labels = wrapper.findAll('text.chart__tick').map((tick) => tick.text())
    expect(labels).toEqual(expect.arrayContaining(['Self', 'Accredited', 'Award']))
  })

  it('formats the axis with its own tick formatter when given one', () => {
    const wrapper = mount(ColumnChart, {
      props: {
        categories: ['Nov 1'],
        series: [{ key: 'collected', label: 'Collected', values: [12000], color: 'red' }],
        caption: 'Amounts collected',
        categoryLabel: 'Date',
        formatValue: (value) => `SAR ${value}`,
        formatTick: (value) => `#${value}`,
      },
    })
    const ticks = wrapper.findAll('text.chart__tick').map((tick) => tick.text())
    expect(ticks).toContain('#0')
    expect(ticks.some((tick) => tick.startsWith('SAR'))).toBe(false)
  })
})

describe('DonutChart', () => {
  it('draws a slice per non-zero share and lists every part with its value and share', () => {
    const wrapper = mount(DonutChart, {
      props: {
        segments: [
          { key: 'gold', label: 'Gold', value: 3, color: 'gold' },
          { key: 'silver', label: 'Silver', value: 0, color: 'silver' },
          { key: 'none', label: 'None', value: 1, color: 'gray' },
        ],
        caption: 'Entities by level',
        categoryLabel: 'Level',
        valueLabel: 'Entities',
        totalLabel: 'entities',
        formatValue: format,
        formatShare: (value) => `${value}%`,
      },
    })
    expect(wrapper.findAll('.chart-donut__slice')).toHaveLength(2)
    expect(wrapper.find('.chart-donut__total-value').text()).toBe('4')
    expect(wrapper.findAll('.chart-legend__item').map((item) => item.text())).toEqual([
      'Gold375%',
      'Silver00%',
      'None125%',
    ])
    expect(wrapper.find('.chart-donut__ring').attributes('aria-label')).toBe('Entities by level')
  })
})

describe('DonutChart links', () => {
  it('turns a legend item into a link when its segment carries a route', () => {
    const globals = createTestGlobals({ locale: 'en' })
    const wrapper = mount(DonutChart, {
      global: { plugins: globals.plugins },
      props: {
        segments: [
          {
            key: 'gold',
            label: 'Gold',
            value: 1,
            color: 'gold',
            to: '/admin/entities?level=gold',
            linkLabel: 'Open gold',
          },
          { key: 'in_progress', label: 'In progress', value: 2, color: 'gray' },
        ],
        caption: 'Entities by level',
        categoryLabel: 'Level',
        valueLabel: 'Entities',
        totalLabel: 'entities',
        formatValue: format,
        formatShare: (value) => `${value}%`,
      },
    })
    const link = wrapper.find('[data-segment="gold"] a')
    expect(link.attributes('href')).toBe('/admin/entities?level=gold')
    expect(link.attributes('aria-label')).toBe('Open gold')
    expect(wrapper.find('[data-segment="in_progress"] a').exists()).toBe(false)
  })
})

describe('SegmentedBar and ProgressList', () => {
  const parts = [
    { key: 'a', label: 'Below 50', value: 3, color: 'blue' },
    { key: 'b', label: 'Not reported', value: 1, color: 'gray' },
    { key: 'c', label: 'Empty', value: 0, color: 'red' },
  ]

  it('splits the bar by share, skips an empty part and lists every part with share and count', () => {
    const wrapper = mount(SegmentedBar, {
      props: {
        segments: parts,
        caption: 'Grades',
        formatValue: format,
        formatShare: (v) => `${v}%`,
      },
    })
    expect(wrapper.findAll('.chart-segmented__part')).toHaveLength(2)
    expect(wrapper.findAll('.chart-segmented__row').map((row) => row.text())).toEqual([
      'Below 5075%(3)',
      'Not reported25%(1)',
      'Empty0%(0)',
    ])
    expect(wrapper.find('.chart-segmented__list').attributes('aria-label')).toBe('Grades')
  })

  it('ranks rows by their share of the whole and says when there is nothing to show', () => {
    const wrapper = mount(ProgressList, {
      props: {
        rows: parts.slice(0, 2),
        caption: 'Regions',
        formatValue: format,
        formatShare: (v) => `${v}%`,
        emptyText: 'Nothing yet',
      },
    })
    expect(
      wrapper.findAll('.chart-progress__fill').map((fill) => fill.attributes('style')),
    ).toEqual(['inline-size: 75%;', 'inline-size: 25%;'])
    const empty = mount(ProgressList, {
      props: {
        rows: [],
        caption: 'Regions',
        formatValue: format,
        formatShare: String,
        emptyText: 'Nothing yet',
      },
    })
    expect(empty.text()).toBe('Nothing yet')
  })
})

describe('GaugeChart', () => {
  it('names the value and fills nothing without one', () => {
    const filled = mount(GaugeChart, {
      props: { value: '79.00', caption: 'Average', valueText: '79%' },
    })
    expect(filled.find('svg').attributes('aria-label')).toBe('Average: 79%')
    expect(filled.find('.chart-gauge__fill').exists()).toBe(true)
    const empty = mount(GaugeChart, { props: { value: null, caption: 'Average', valueText: '—' } })
    expect(empty.find('.chart-gauge__fill').exists()).toBe(false)
  })
})
