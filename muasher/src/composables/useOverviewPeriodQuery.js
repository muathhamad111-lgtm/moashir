import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  DEFAULT_OVERVIEW_PERIOD,
  OVERVIEW_PERIODS,
  OVERVIEW_PRESETS,
  OVERVIEW_QUERY,
} from '@/constants/dashboardOverview'
import { calendarOf } from '@/utils/dashboard/overviewCalendar'
import { riyadhDateKey } from '@/utils/datetime'

const Q = OVERVIEW_QUERY
// The calendar fields each period type is chosen by.
const FIELDS = Object.freeze({
  [OVERVIEW_PERIODS.MONTH]: [Q.YEAR, Q.MONTH],
  [OVERVIEW_PERIODS.QUARTER]: [Q.YEAR, Q.QUARTER],
  [OVERVIEW_PERIODS.YEAR]: [Q.YEAR],
  [OVERVIEW_PERIODS.CUSTOM]: [Q.FROM, Q.TO],
})
const CLEARED = Object.freeze({
  [Q.YEAR]: undefined,
  [Q.MONTH]: undefined,
  [Q.QUARTER]: undefined,
  [Q.FROM]: undefined,
  [Q.TO]: undefined,
})

function queryText(value) {
  return typeof value === 'string' ? value : ''
}

function queryNumber(value) {
  const number = Number.parseInt(queryText(value), 10)
  return Number.isInteger(number) ? number : null
}

// The chosen month, quarter and year; the current ones where the URL names none.
function selectionOf(query, today) {
  return {
    year: queryNumber(query[Q.YEAR]) ?? today.year,
    month: queryNumber(query[Q.MONTH]) ?? today.month,
    quarter: queryNumber(query[Q.QUARTER]) ?? today.quarter,
  }
}

function textValues(fields) {
  return Object.fromEntries(Object.entries(fields).map(([key, value]) => [key, `${value}`]))
}

// The API request: the period type and only the fields the URL names for it.
function requestOf(period, query) {
  const named = FIELDS[period].filter((field) => queryText(query[field]))
  return Object.fromEntries([
    [Q.PERIOD, period],
    ...named.map((field) => [field, queryText(query[field])]),
  ])
}

/**
 * The reporting period of a dashboard overview, kept in the URL: a period type with its month,
 * quarter or year (the current one when the URL names none), or a custom range.
 */
export function useOverviewPeriodQuery() {
  const route = useRoute()
  const router = useRouter()
  const today = calendarOf(riyadhDateKey(new Date()))

  const period = computed(() => {
    const value = queryText(route.query[Q.PERIOD])
    return OVERVIEW_PRESETS.includes(value) ? value : DEFAULT_OVERVIEW_PERIOD
  })
  const selection = computed(() => selectionOf(route.query, today))
  const customFrom = computed(() => queryText(route.query[Q.FROM]))
  const customTo = computed(() => queryText(route.query[Q.TO]))
  const isCustom = computed(() => period.value === OVERVIEW_PERIODS.CUSTOM)
  const isComplete = computed(() => !isCustom.value || Boolean(customFrom.value && customTo.value))

  const request = computed(() => requestOf(period.value, route.query))

  function replaceQuery(changes) {
    const query = { ...route.query, ...changes }
    Object.keys(changes).forEach((key) => changes[key] === undefined && delete query[key])
    return router.replace({ query })
  }

  function choosePreset(preset) {
    return replaceQuery({ ...CLEARED, [Q.PERIOD]: preset })
  }

  function chooseCalendar(fields) {
    return replaceQuery({ ...CLEARED, [Q.PERIOD]: period.value, ...textValues(fields) })
  }

  function applyCustom(from, to) {
    return replaceQuery({
      ...CLEARED,
      [Q.PERIOD]: OVERVIEW_PERIODS.CUSTOM,
      [Q.FROM]: from,
      [Q.TO]: to,
    })
  }

  return {
    period,
    selection,
    customFrom,
    customTo,
    isCustom,
    isComplete,
    request,
    choosePreset,
    chooseCalendar,
    applyCustom,
  }
}
