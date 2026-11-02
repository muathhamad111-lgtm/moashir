import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import FeeSummary from '@/components/payments/FeeSummary.vue'
import { createTestGlobals } from '@/test-utils'

function mountSummary(props = {}, slots = {}) {
  return mount(FeeSummary, {
    props: { amount: 5000, currency: 'SAR', purpose: 'accredited', ...props },
    slots,
    global: createTestGlobals({ locale: 'en' }),
  })
}

describe('FeeSummary', () => {
  it('formats the amount and shows the currency label', () => {
    const wrapper = mountSummary({ amount: 5000 })
    expect(wrapper.find('.fee-summary__amount-value').text()).toBe('5,000')
    expect(wrapper.find('.fee-summary__amount-currency').text()).toBe('SAR')
  })

  it('renders a status badge only when a status is provided', () => {
    const without = mountSummary()
    expect(without.find('.badge').exists()).toBe(false)
    const withStatus = mountSummary({ status: 'paid' })
    expect(withStatus.find('.badge').text()).toBe('Paid')
  })

  it('renders line items when provided', () => {
    const wrapper = mountSummary({
      lineItems: [{ label: 'Assessment', value: '5,000' }],
    })
    const line = wrapper.find('.fee-summary__line')
    expect(line.text()).toContain('Assessment')
    expect(line.text()).toContain('5,000')
  })

  it('renders the action slot', () => {
    const wrapper = mountSummary({}, { action: '<button class="pay">Pay</button>' })
    expect(wrapper.find('.fee-summary__action .pay').exists()).toBe(true)
  })
})
