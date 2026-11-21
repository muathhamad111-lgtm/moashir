import { TRANSACTION_STATUS_FILTERS, TRANSACTION_TYPES } from '@/constants/finance'

// Type and status filters accept several values. The filter state keeps them as one
// comma-separated string (filters stay strings); the API receives them as lists.
export const LIST_FILTERS = Object.freeze({
  type: TRANSACTION_TYPES,
  status: TRANSACTION_STATUS_FILTERS,
})

export function splitFilterValue(value) {
  return value ? String(value).split(',').filter(Boolean) : []
}

// Keeps only allowed values, in the catalogue's order, without duplicates.
export function joinFilterValues(values, allowed) {
  const chosen = new Set(values)
  return allowed.filter((value) => chosen.has(value)).join(',')
}

export function listFilterParams(params) {
  const next = { ...params }
  Object.keys(LIST_FILTERS).forEach((key) => {
    const name = `filter[${key}]`
    if (next[name]) next[name] = splitFilterValue(next[name])
  })
  return next
}

// A link may open the operations already filtered (e.g. the dashboard's refunds card sets ?type=refund).
export function listFiltersFromQuery(query) {
  return Object.fromEntries(
    Object.entries(LIST_FILTERS)
      .map(([key, allowed]) => [key, joinFilterValues(queryValues(query?.[key]), allowed)])
      .filter(([, value]) => value),
  )
}

function queryValues(raw) {
  const values = Array.isArray(raw) ? raw : [raw]
  return values.filter((value) => typeof value === 'string').flatMap(splitFilterValue)
}
