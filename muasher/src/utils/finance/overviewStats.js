import { FINANCE_OCCASIONAL_STATUSES, FINANCE_STATUS_ORDER } from '@/constants/finance'

// API keys arrive camel-cased (under_review → underReview, bank_transfer → bankTransfer); the
// filters keep the snake values.
function camelOf(key) {
  return key.replace(/_(\w)/g, (_, letter) => letter.toUpperCase())
}

function countOf(statusCounts, status) {
  return statusCounts?.[camelOf(status)] ?? 0
}

const NO_FIGURES = Object.freeze({ count: 0, paidTotal: '0.00', statusCounts: {} })

// The figures of one channel, or of every operation when no channel is chosen ('').
export function channelFigures(stats, channel) {
  if (!channel) {
    return { count: stats?.transactionsCount ?? 0, statusCounts: stats?.statusCounts ?? {} }
  }
  return { ...NO_FIGURES, ...stats?.channels?.[camelOf(channel)] }
}

// How many operations, across both channels, are in one status.
export function statusCountOf(stats, status) {
  return countOf(stats?.statusCounts, status)
}

// Each status with its count in the chosen channel, in the bars' order; an occasional status joins
// only when it has operations.
export function statusCountsIn(stats, channel) {
  const { statusCounts } = channelFigures(stats, channel)
  const occasional = FINANCE_OCCASIONAL_STATUSES.filter((status) => countOf(statusCounts, status))
  return [...FINANCE_STATUS_ORDER, ...occasional].map((status) => ({
    status,
    count: countOf(statusCounts, status),
  }))
}

// The statuses a channel's operations are in, for its distribution bar (empty ones left out).
export function statusDistribution(stats, channel) {
  return statusCountsIn(stats, channel).filter((chip) => chip.count > 0)
}
