import { OVERVIEW_STATUSES } from '@/constants/finance'

// API keys arrive camel-cased (under_review → underReview); the filters keep the snake values.
function camelOf(key) {
  return key.replace(/_(\w)/g, (_, letter) => letter.toUpperCase())
}

function cellsOf(statusCounts, statuses) {
  return statuses.map((status) => ({ status, count: statusCounts?.[camelOf(status)] ?? 0 }))
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
