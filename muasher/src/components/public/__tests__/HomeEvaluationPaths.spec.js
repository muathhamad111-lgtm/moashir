import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import HomeEvaluationPaths from '@/components/public/HomeEvaluationPaths.vue'
import { createTestGlobals } from '@/test-utils'
import { servicePricesService } from '@/services/servicePricesService'

vi.mock('@/services/servicePricesService', () => ({
  servicePricesService: { getServicePrices: vi.fn() },
}))

const MONEY_FORMATS = {
  money: {
    style: 'decimal',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
    useGrouping: true,
    numberingSystem: 'latn',
  },
}

function localized(content) {
  return typeof content === 'string' ? content : (content?.en ?? '')
}

function path(id, meta, ctaUrl = '/assessment/self') {
  return {
    id,
    icon: 'flag',
    title: { ar: `مسار ${id}`, en: `Path ${id}` },
    description: { ar: 'وصف', en: 'Description' },
    ctaLabel: { ar: 'ابدأ', en: 'Start' },
    ctaUrl,
    meta,
  }
}

const section = {
  title: { ar: 'مسارات التقييم', en: 'Assessment paths' },
  items: [
    path('self', { accent: 'calm', points: [{ en: 'No evidence' }] }),
    path('accredited', { accent: 'accredited', availability: 'live' }, '/accredited'),
    path('advanced', { accent: 'award', availability: 'coming_soon' }),
  ],
}

function pricing(priceBeforeVat) {
  return { data: { accredited: priceBeforeVat ? { priceBeforeVat, currency: 'SAR' } : null } }
}

async function mountPaths(locale = 'en', prices = pricing('1000.00')) {
  servicePricesService.getServicePrices.mockResolvedValue(prices)
  const globals = createTestGlobals({ locale })
  globals.i18n.global.setNumberFormat(locale, MONEY_FORMATS)
  const wrapper = mount(HomeEvaluationPaths, { props: { section, localized }, global: globals })
  await flushPromises()
  return { wrapper, globals }
}

describe('HomeEvaluationPaths', () => {
  it('labels each path: free self, available accredited and coming-soon advanced', async () => {
    const { wrapper } = await mountPaths()
    const cards = wrapper.findAll('.home-path')
    expect(cards.map((card) => card.find('.home-path__availability').text())).toEqual([
      'Free',
      'Available now',
      'Coming soon',
    ])
    expect(cards[1].classes()).toContain('home-path--accredited')
    expect(cards[0].find('.home-path__point').text()).toBe('No evidence')
  })

  it('disables the action of unavailable paths and shows no demo marker', async () => {
    const { wrapper } = await mountPaths('ar')
    const cards = wrapper.findAll('.home-path')
    expect(cards[2].find('.btn').attributes('disabled')).toBeDefined()
    expect(cards[2].find('.btn').text()).toBe('قريبًا')
    expect(wrapper.text()).not.toContain('عرض تجريبي')
  })

  it('shows the accredited price before VAT with two decimals from the prices endpoint', async () => {
    const { wrapper } = await mountPaths()
    const price = wrapper.find('.home-path--accredited .home-path__price')
    expect(price.find('.home-path__amount').text()).toBe('1,000.00')
    expect(price.find('.home-path__price-note').text()).toBe('SAR — VAT not included')
    expect(wrapper.findAll('.home-path__price')).toHaveLength(1)
  })

  it('hides the price line when the accredited price is not set', async () => {
    const { wrapper } = await mountPaths('en', pricing(null))
    expect(wrapper.find('.home-path__price').exists()).toBe(false)
  })

  it('hides the price line when the prices cannot be read', async () => {
    servicePricesService.getServicePrices.mockRejectedValue(new Error('offline'))
    const wrapper = mount(HomeEvaluationPaths, {
      props: { section, localized },
      global: createTestGlobals({ locale: 'en' }),
    })
    await flushPromises()
    expect(wrapper.find('.home-path__price').exists()).toBe(false)
  })

  it('routes the available path actions through the router', async () => {
    const { wrapper, globals } = await mountPaths()
    const push = vi.spyOn(globals.router, 'push').mockResolvedValue()
    await wrapper.findAll('.home-path')[1].find('.btn').trigger('click')
    expect(push).toHaveBeenCalledWith('/accredited')
  })
})
