import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import FinanceOverviewCards from '@/components/finance/FinanceOverviewCards.vue'
import { createTestGlobals } from '@/test-utils'

const STATS = {
  transactionsCount: 9,
  totalIncome: '5750.00',
  totalVat: '750.00',
  totalRefunded: '1150.00',
  statusCounts: { paid: 4, underReview: 2, awaitingPayment: 1, rejected: 1, failed: 1 },
  channels: {
    electronic: { count: 5, paidTotal: '4600.00', statusCounts: { paid: 3, failed: 1 } },
    bankTransfer: { count: 4, paidTotal: '1150.00', statusCounts: { paid: 1, underReview: 2 } },
  },
}

function mountCards(props) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  return mount(FinanceOverviewCards, { props, global: globals })
}

describe('FinanceOverviewCards', () => {
  it('shows the revenue with its VAT and refunds, and the operations by status and channel', () => {
    const wrapper = mountCards({ stats: STATS })
    expect(wrapper.find('.finance-overview__revenue').text()).toContain('5,750.00')
    expect(wrapper.find('.finance-overview__revenue').text()).toContain('750.00')
    const titles = wrapper.findAll('.finance-overview__label').map((title) => title.text())
    expect(titles).toEqual([
      'Total revenue',
      'Total operations',
      'Electronic payment',
      'Bank transfer',
    ])
    const transfers = wrapper.findAll('.finance-overview__split-card')[2]
    expect(transfers.findAll('.finance-overview__count').map((count) => count.text())).toEqual([
      '1',
      '2',
      '0',
      '0',
    ])
    expect(transfers.find('.finance-overview__amount').text()).toContain('1,150.00')
  })

  it('filters the list by a status, and by the channel on a channel card', async () => {
    const wrapper = mountCards({ stats: STATS })
    const cards = wrapper.findAll('.finance-overview__split-card')
    await cards[0].findAll('.finance-overview__cell')[1].trigger('click')
    await cards[2].findAll('.finance-overview__cell')[1].trigger('click')
    expect(wrapper.emitted('select')).toEqual([
      [{ status: 'under_review' }],
      [{ status: 'under_review', type: 'bank_transfer' }],
    ])
    expect(cards[0].findAll('.finance-overview__cell')[1].attributes('aria-label')).toContain('2')
  })

  it('shows placeholders while the figures load', () => {
    const wrapper = mountCards({ stats: null, loading: true })
    expect(wrapper.find('.finance-overview__value').exists()).toBe(false)
    expect(wrapper.find('.finance-overview__detail').exists()).toBe(false)
    expect(wrapper.find('.finance-overview').attributes('aria-busy')).toBe('true')
  })
})
