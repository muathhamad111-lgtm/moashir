import { OVERVIEW_STATUSES, TRANSACTION_STATUS_FILTERS } from '@/constants/finance'

// API keys arrive camel-cased (under_review → underReview); the filters keep the snake values.
function camelOf(key) {
  return key.replace(/_(\w)/g, (_, letter) => letter.toUpperCase())
}

function countOf(statusCounts, status) {
  return statusCounts?.[camelOf(status)] ?? 0
}

// The card's main statuses, then every other status that has operations, in the filter order.
function cellsOf(statusCounts, mainStatuses) {
  const others = TRANSACTION_STATUS_FILTERS.filter(
    (status) => !mainStatuses.includes(status) && countOf(statusCounts, status) > 0,
  )
  return [...mainStatuses, ...others].map((status) => ({
    status,
    count: countOf(statusCounts, status),
  }))
}

export function overallSplit(stats) {
  return cellsOf(stats?.statusCounts, OVERVIEW_STATUSES.all)
}

export function channelSummary(stats, channel) {
  const figures = stats?.channels?.[camelOf(channel)]
  return {
    channel,
    count: figures?.count ?? 0,
    paidTotal: figures?.paidTotal ?? '0.00',
    cells: cellsOf(figures?.statusCounts, OVERVIEW_STATUSES[channel]),
  }
}
