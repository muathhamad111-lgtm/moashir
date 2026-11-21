import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import OverviewGeography from '../OverviewGeography.vue'
import { createTestGlobals } from '@/test-utils'
import { toExecutiveOverview } from '@/services/mappers/dashboardOverview'
import { stepOf } from '@/composables/useOverviewGeography'

const RIYADH = { ar: 'منطقة الرياض', en: 'Riyadh Region' }
const MAKKAH = { ar: 'منطقة مكة المكرمة', en: 'Makkah Region' }
const TABUK = { ar: 'منطقة تبوك', en: 'Tabuk Region' }

const OVERVIEW = toExecutiveOverview({
  geography: {
    regions: [
      {
        regionId: 1,
        code: 'riyadh',
        name: RIYADH,
        entities: 8,
        requests: 1,
        averagePercent: '71.50',
        collected: '1150.00',
      },
      {
        regionId: 2,
        code: 'makkah',
        name: MAKKAH,
        entities: 2,
        requests: 4,
        averagePercent: null,
        collected: '4600.00',
      },
      {
        regionId: 7,
        code: 'tabuk',
        name: TABUK,
        entities: 0,
        requests: 0,
        averagePercent: null,
        collected: '0.00',
      },
    ],
    cities: [
      {
        cityId: 1,
        regionId: 1,
        code: 'riyadh',
        name: { ar: 'الرياض', en: 'Riyadh' },
        latitude: 24.638,
        longitude: 46.715,
        entities: 6,
        requests: 1,
        averagePercent: '71.50',
        collected: '1150.00',
      },
      {
        cityId: 2,
        regionId: 1,
        code: 'kharj',
        name: { ar: 'الخرج', en: 'Al-Kharj' },
        latitude: null,
        longitude: null,
        entities: 2,
        requests: 0,
        averagePercent: null,
        collected: '0.00',
      },
      {
        cityId: 3,
        regionId: 2,
        code: 'jeddah',
        name: { ar: 'جدة', en: 'Jeddah' },
        latitude: 21.529,
        longitude: 39.161,
        entities: 2,
        requests: 4,
        averagePercent: null,
        collected: '4600.00',
      },
    ],
  },
})

function mountMap(locale = 'en') {
  const globals = createTestGlobals({ locale })
  return mount(OverviewGeography, {
    props: { overview: OVERVIEW },
    global: { plugins: globals.plugins },
  })
}

const stepOfRegion = (wrapper, code) =>
  wrapper
    .find(`[data-region="${code}"]`)
    .classes()
    .find((name) => name.startsWith('region-map__region--step-'))

describe('OverviewGeography', () => {
  it('shades the regions by registered entities and selects the strongest one', () => {
    const wrapper = mountMap()
    expect(wrapper.findAll('.region-map__region')).toHaveLength(3)
    expect(stepOfRegion(wrapper, 'riyadh')).toBe('region-map__region--step-4')
    expect(stepOfRegion(wrapper, 'makkah')).toBe('region-map__region--step-1')
    expect(stepOfRegion(wrapper, 'tabuk')).toBe('region-map__region--step-0')
    expect(wrapper.find('[data-region="riyadh"]').attributes('aria-pressed')).toBe('true')
    expect(wrapper.find('[data-metric="entities"]').attributes('aria-pressed')).toBe('true')
    expect(wrapper.findAll('[data-rank]').map((row) => row.attributes('data-rank'))).toEqual([
      'riyadh',
      'makkah',
    ])
  })

  it('lists a region’s cities, drawing only those with coordinates', () => {
    const wrapper = mountMap()
    const cities = wrapper.find('[data-testid="geography-cities"]')
    expect(cities.find('h4').text()).toBe('Cities of Riyadh Region')
    expect(cities.findAll('.overview-geography__city').map((city) => city.text())).toEqual([
      'Riyadh6',
      'Al-Kharj2',
    ])
    expect(wrapper.findAll('.region-map__city').map((dot) => dot.attributes('data-city'))).toEqual([
      '1-riyadh',
      '2-jeddah',
    ])
  })

  it('switches the measure and follows a chosen region from the map or the ranking', async () => {
    const wrapper = mountMap()
    await wrapper.find('[data-metric="collected"]').trigger('click')
    expect(stepOfRegion(wrapper, 'makkah')).toBe('region-map__region--step-4')
    expect(wrapper.find('[data-rank="makkah"]').text()).toContain('SAR')

    await wrapper.find('[data-region="riyadh"]').trigger('keydown', { key: 'Enter' })
    expect(wrapper.find('[data-testid="geography-cities"] h4').text()).toBe(
      'Cities of Riyadh Region',
    )
    await wrapper.find('[data-rank="makkah"]').trigger('click')
    expect(wrapper.find('[data-region="makkah"]').attributes('aria-pressed')).toBe('true')
  })

  it('leaves a region without results neutral on the average measure', async () => {
    const wrapper = mountMap()
    await wrapper.find('[data-metric="averagePercent"]').trigger('click')
    expect(stepOfRegion(wrapper, 'makkah')).toBe('region-map__region--step-0')
    expect(wrapper.find('[data-region="makkah"]').attributes('aria-label')).toContain('—')
  })

  it('steps a value against the largest one', () => {
    expect([stepOf(0, 10), stepOf(1, 10), stepOf(3, 10), stepOf(10, 10), stepOf(5, 0)]).toEqual([
      0, 1, 2, 4, 0,
    ])
  })
})
