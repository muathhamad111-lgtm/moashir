import { describe, expect, it } from 'vitest'
import { entityInitials } from '@/utils/finance/entityInitials'
import { channelFigures, statusCountsIn, statusDistribution } from '@/utils/finance/overviewStats'

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
  it('counts every operation, or one channel, with its paid amount', () => {
    expect(channelFigures(STATS, '').count).toBe(9)
    expect(channelFigures(STATS, 'bank_transfer')).toEqual({
      count: 4,
      paidTotal: '1150.00',
      statusCounts: { paid: 1, underReview: 2, rejected: 1 },
    })
    expect(channelFigures(null, 'electronic')).toEqual({
      count: 0,
      paidTotal: '0.00',
      statusCounts: {},
    })
  })

  it('gives the status chips in order, with the counts of the chosen channel', () => {
    expect(statusCountsIn(STATS, 'electronic')).toEqual([
      { status: 'paid', count: 3 },
      { status: 'under_review', count: 0 },
      { status: 'awaiting_payment', count: 1 },
      { status: 'rejected', count: 0 },
      { status: 'cancelled', count: 0 },
      { status: 'failed', count: 1 },
    ])
  })

  it('adds the refunded chip only where refunded operations exist', () => {
    expect(statusCountsIn(API_STATS, 'bank_transfer').at(-1)).toEqual({
      status: 'refunded',
      count: 2,
    })
    expect(statusCountsIn(API_STATS, 'electronic').map((chip) => chip.status)).not.toContain(
      'refunded',
    )
  })

  it('splits each channel bar by the statuses it has, adding up to its total', () => {
    const electronic = statusDistribution(API_STATS, 'electronic')
    const bank = statusDistribution(API_STATS, 'bank_transfer')
    expect(electronic).toEqual([
      { status: 'paid', count: 5 },
      { status: 'cancelled', count: 1 },
    ])
    expect(sumOf(bank)).toBe(API_STATS.channels.bankTransfer.count)
    expect(sumOf(statusDistribution(API_STATS, ''))).toBe(API_STATS.transactionsCount)
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
