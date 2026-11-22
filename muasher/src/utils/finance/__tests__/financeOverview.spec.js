import { describe, expect, it } from 'vitest'
import { entityInitials } from '@/utils/finance/entityInitials'
import { channelSummary, overallSplit } from '@/utils/finance/overviewStats'

const STATS = {
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

describe('finance overview figures', () => {
  it('splits all operations by the ledger statuses, in the cards order', () => {
    expect(overallSplit(STATS)).toEqual([
      { status: 'paid', count: 4 },
      { status: 'under_review', count: 2 },
      { status: 'awaiting_payment', count: 1 },
      { status: 'rejected', count: 1 },
      { status: 'failed', count: 1 },
    ])
  })

  it('gives each channel its own total, paid amount and statuses', () => {
    expect(channelSummary(STATS, 'bank_transfer')).toEqual({
      channel: 'bank_transfer',
      count: 4,
      paidTotal: '1150.00',
      cells: [
        { status: 'paid', count: 1 },
        { status: 'under_review', count: 2 },
        { status: 'awaiting_payment', count: 0 },
        { status: 'rejected', count: 1 },
      ],
    })
    expect(channelSummary(null, 'electronic').cells.every((cell) => cell.count === 0)).toBe(true)
  })
})

describe('entityInitials', () => {
  it('skips the organisation word and the Arabic article', () => {
    expect(entityInitials('جمعية النماء الخيرية')).toBe('نخ')
    expect(entityInitials('Society of Ruwad')).toBe('OR')
    expect(entityInitials('Ruwad Al-Ataa Foundation')).toBe('RA')
    expect(entityInitials('Namaa Charity')).toBe('NC')
    expect(entityInitials('')).toBe('')
  })
})
