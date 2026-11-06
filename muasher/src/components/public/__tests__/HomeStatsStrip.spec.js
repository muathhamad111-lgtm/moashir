import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import HomeStatsStrip from '@/components/public/HomeStatsStrip.vue'
import { createTestGlobals } from '@/test-utils'

const getFeatured = vi.fn()

vi.mock('@/services/kpisService', () => ({
  kpisService: {
    getFeatured: (...args) => getFeatured(...args),
  },
}))

function localized(content) {
  return typeof content === 'string' ? content : (content?.en ?? '')
}

function makeKpi(id, value) {
  return {
    id,
    slug: `kpi-${id}`,
    name: { ar: `مؤشر ${id}`, en: `Indicator ${id}` },
    value,
    unit: { ar: 'جمعية', en: 'associations' },
  }
}

function mountStrip() {
  return mount(HomeStatsStrip, {
    props: { localized },
    global: createTestGlobals({ locale: 'en' }),
  })
}

beforeEach(() => {
  getFeatured.mockReset()
})

describe('HomeStatsStrip', () => {
  it('shows up to four featured published indicators with their values, units and names', async () => {
    getFeatured.mockResolvedValue({
      data: [
        makeKpi(1, 486),
        makeKpi(2, '132'),
        makeKpi(3, 4800000),
        makeKpi(4, 71.5),
        makeKpi(5, 9),
      ],
    })
    const wrapper = mountStrip()
    await flushPromises()

    const items = wrapper.findAll('.home-stats__item')
    expect(items).toHaveLength(4)
    expect(wrapper.find('.home-stats').attributes('aria-label')).toBe(
      'Featured performance indicators',
    )
    expect(items[0].find('.home-stats__value').text()).toBe('486')
    expect(items[1].find('.home-stats__value').text()).toBe('132')
    expect(items[2].find('.home-stats__value').text()).toMatch(/^4,?800,?000$/)
    expect(items[0].find('.home-stats__unit').text()).toBe('associations')
    expect(items[3].find('.home-stats__name').text()).toBe('Indicator 4')
    expect(items[0].find('.home-stats__value').attributes('dir')).toBe('ltr')
    expect(wrapper.find('.home-stats-band').exists()).toBe(true)
    expect(wrapper.text()).not.toMatch(/[٠-٩]/)
  })

  it('stays hidden when fewer than three indicators are featured', async () => {
    getFeatured.mockResolvedValue({ data: [makeKpi(1, 486), makeKpi(2, 132)] })
    const wrapper = mountStrip()
    await flushPromises()

    expect(wrapper.find('.home-stats').exists()).toBe(false)
    expect(wrapper.find('.home-stats-band').exists()).toBe(false)
  })

  it('stays hidden when the indicators cannot be loaded', async () => {
    getFeatured.mockRejectedValue(new Error('network'))
    const wrapper = mountStrip()
    await flushPromises()

    expect(wrapper.find('.home-stats').exists()).toBe(false)
  })
})
