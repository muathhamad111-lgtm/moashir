import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BagIntro from '@/components/bag/BagIntro.vue'
import { createTestGlobals } from '@/test-utils'
import { seedBagProduct } from '@/mocks/fixtures/bagProduct'

function localized(content) {
  if (!content) return ''
  return typeof content === 'string' ? content : content.en || content.ar || ''
}

const product = seedBagProduct()

function mountIntro(props = {}) {
  return mount(BagIntro, {
    props: {
      product,
      pricing: product.pricing,
      localized,
      eligible: false,
      ...props,
    },
    global: createTestGlobals({ locale: 'en' }),
  })
}

describe('BagIntro', () => {
  it('renders the marketing content: title, how-it-works steps, outputs, and FAQ', () => {
    const wrapper = mountIntro()
    expect(wrapper.find('.bag-intro__title').text()).toBe('Quick Excellence Kit')
    expect(wrapper.findAll('.bag-intro__step')).toHaveLength(product.marketing.howItWorks.length)
    expect(wrapper.findAll('.bag-intro__output')).toHaveLength(product.marketing.outputs.length)
    expect(wrapper.findAll('.accordion__item')).toHaveLength(product.marketing.faqs.length)
  })

  it('shows the price breakdown (before tax + VAT) and the tax-inclusive total', () => {
    const wrapper = mountIntro()
    const lines = wrapper.findAll('.fee-summary__line').map((l) => l.text())
    expect(lines).toHaveLength(2)
    expect(lines[0]).toContain('Price before tax')
    expect(lines[1]).toContain('VAT')
    expect(wrapper.find('.fee-summary__amount-value').text().replace(/,/g, '')).toContain('2875')
  })

  it('links the subscribe CTA to the Kit checkout when eligible', () => {
    const wrapper = mountIntro({ eligible: true })
    const link = wrapper.find('.bag-intro__pay-link')
    expect(link.text()).toContain('Subscribe & pay')
    expect(link.attributes('href')).toBe('/bag/pay')
  })

  it('shows the complete-accredited link (not the pay button) when not eligible', () => {
    const wrapper = mountIntro({ eligible: false })
    expect(wrapper.find('.bag-intro__return-link').exists()).toBe(true)
    expect(wrapper.text()).toContain('Complete the accredited assessment')
  })
})
