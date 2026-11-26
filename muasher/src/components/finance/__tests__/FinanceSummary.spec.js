import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import FinanceSummaryCards from '../FinanceSummaryCards.vue'
import TransactionFilters from '../TransactionFilters.vue'
import { createTestGlobals } from '@/test-utils'

const STATS = {
  totalIncome: '3450.00',
  totalVat: '450.00',
  totalRefunded: '0.00',
  transactionsCount: 9,
  statusCounts: { paid: 4, underReview: 2, awaitingPayment: 1, rejected: 1, failed: 1 },
  channels: {
    electronic: {
      count: 5,
      paidTotal: '2300.00',
      statusCounts: { paid: 3, awaitingPayment: 1, failed: 1 },
    },
    bankTransfer: {
      count: 4,
      paidTotal: '1150.00',
      statusCounts: { paid: 1, underReview: 2, rejected: 1 },
    },
  },
}

function mountWith(component, props, locale = 'en') {
  return mount(component, { props, global: createTestGlobals({ locale }) })
}

describe('finance summary cards', () => {
  it('shows three cards: revenue and each channel with its paid amount and status bar', () => {
    const wrapper = mountWith(FinanceSummaryCards, { stats: STATS })
    const cards = wrapper.findAll('.finance-summary__card')
    expect(cards.map((card) => card.attributes('data-card'))).toEqual([
      'revenue',
      'electronic',
      'bank_transfer',
    ])
    expect(cards[2].text()).toContain('1,150.00')
    expect(cards[2].text()).toContain('out of 4 operations')
    expect(
      cards[2].findAll('.status-bar__part').map((part) => part.attributes('data-status')),
    ).toEqual(['paid', 'under_review', 'rejected'])
    expect(cards[2].find('.status-bar__list').text()).toContain('Awaiting approval: 2')
    expect(wrapper.find('.finance-overview__split').exists()).toBe(false)
  })
})

describe('finance filter panel', () => {
  it('counts each type and status of the operations in its options', () => {
    const wrapper = mountWith(TransactionFilters, {
      stats: STATS,
      filters: { type: '', status: '', from: '', to: '', purpose: '', mode: '', entity_id: '' },
      sort: '-created_at',
    })
    const options = (key) =>
      wrapper
        .findAllComponents({ name: 'AppCombobox' })
        .find((combobox) => combobox.classes().includes(`transaction-filters__${key}`))
        .props('options')
        .map((option) => option.label)
    expect(options('type')).toContain('Bank transfer (4)')
    expect(options('status').some((label) => label.endsWith('(4)'))).toBe(true)
  })
})
