import { LEVEL_BUCKETS, LEVEL_FILTER_VALUES } from '@/constants/levels'

const PERCENT = 100

function countsByKey(items) {
  const entries = (Array.isArray(items) ? items : []).map((item) => [
    item?.key,
    Number(item?.count ?? 0),
  ])
  return new Map(entries)
}

export function levelBarRows(items) {
  const counts = countsByKey(items)
  const rows = Object.entries(LEVEL_BUCKETS).map(([key, tier]) => ({
    key,
    tier,
    count: counts.get(key) ?? 0,
    filterable: LEVEL_FILTER_VALUES.includes(key),
  }))
  const max = Math.max(1, ...rows.map((row) => row.count))
  return rows.map((row) => ({ ...row, ratio: Math.round((row.count / max) * PERCENT) }))
}

export function fillStartOf(ratio, isRtl) {
  return isRtl ? `${PERCENT - ratio}%` : '0'
}
