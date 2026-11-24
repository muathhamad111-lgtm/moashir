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

// As the summary API sends it: every ledger status, zeros included (created and pending folded into
// awaiting_payment). The operator's case: six electronic operations, five paid and one expired.
const API_STATS = {
  transactionsCount: 11,
  statusCounts: {
    paid: 7,
    underReview: 1,
    awaitingPayment: 0,
    rejected: 0,
    failed: 0,
    cancelled: 1,
    refunded: 2,
  },
  channels: {
    electronic: {
      count: 6,
      paidTotal: '5750.00',
      statusCounts: {
        paid: 5,
        underReview: 0,
        awaitingPayment: 0,
        rejected: 0,
        failed: 0,
        cancelled: 1,
        refunded: 0,
      },
    },
    bankTransfer: {
      count: 5,
      paidTotal: '2300.00',
      statusCounts: {
        paid: 2,
        underReview: 1,
        awaitingPayment: 0,
        rejected: 0,
        failed: 0,
        cancelled: 0,
        refunded: 2,
      },
    },
  },
}

function sumOf(cells) {
  return cells.reduce((total, cell) => total + cell.count, 0)
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

  it('adds every status that occurs to the cards, so each card adds up to its total', () => {
    const electronic = channelSummary(API_STATS, 'electronic')
    const bank = channelSummary(API_STATS, 'bank_transfer')
    expect(sumOf(electronic.cells)).toBe(electronic.count)
    expect(sumOf(bank.cells)).toBe(bank.count)
    expect(sumOf(overallSplit(API_STATS))).toBe(API_STATS.transactionsCount)
    expect(electronic.cells.map((cell) => cell.status)).toEqual([
      'paid',
      'awaiting_payment',
      'failed',
      'cancelled',
    ])
    expect(bank.cells.at(-1)).toEqual({ status: 'refunded', count: 2 })
  })
})
