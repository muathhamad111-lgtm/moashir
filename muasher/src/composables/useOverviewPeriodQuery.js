import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  DEFAULT_OVERVIEW_PERIOD,
  OVERVIEW_PERIODS,
  OVERVIEW_PRESETS,
  OVERVIEW_QUERY,
} from '@/constants/dashboardOverview'

const Q = OVERVIEW_QUERY

function queryText(value) {
  return typeof value === 'string' ? value : ''
}

/**
 * The reporting period of a dashboard overview, kept in the URL: a preset, or a custom range.
 */
export function useOverviewPeriodQuery() {
  const route = useRoute()
  const router = useRouter()

  const period = computed(() => {
    const value = queryText(route.query[Q.PERIOD])
    return OVERVIEW_PRESETS.includes(value) ? value : DEFAULT_OVERVIEW_PERIOD
  })
  const customFrom = computed(() => queryText(route.query[Q.FROM]))
  const customTo = computed(() => queryText(route.query[Q.TO]))
  const isCustom = computed(() => period.value === OVERVIEW_PERIODS.CUSTOM)
  const isComplete = computed(() => !isCustom.value || Boolean(customFrom.value && customTo.value))

  const request = computed(() =>
    isCustom.value
      ? { [Q.PERIOD]: period.value, [Q.FROM]: customFrom.value, [Q.TO]: customTo.value }
      : { [Q.PERIOD]: period.value },
  )

  function replaceQuery(changes) {
    const query = { ...route.query, ...changes }
    Object.keys(changes).forEach((key) => changes[key] === undefined && delete query[key])
    return router.replace({ query })
  }

  function choosePreset(preset) {
    if (preset === OVERVIEW_PERIODS.CUSTOM) return replaceQuery({ [Q.PERIOD]: preset })
    return replaceQuery({ [Q.PERIOD]: preset, [Q.FROM]: undefined, [Q.TO]: undefined })
  }

  function applyCustom(from, to) {
    return replaceQuery({ [Q.PERIOD]: OVERVIEW_PERIODS.CUSTOM, [Q.FROM]: from, [Q.TO]: to })
  }

  return {
    period,
    customFrom,
    customTo,
    isCustom,
    isComplete,
    request,
    choosePreset,
    applyCustom,
  }
}
