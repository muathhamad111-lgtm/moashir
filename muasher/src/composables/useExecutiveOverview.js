import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { dashboardService } from '@/services/dashboardService'
import {
  DEFAULT_OVERVIEW_PERIOD,
  OVERVIEW_PERIODS,
  OVERVIEW_PRESETS,
  OVERVIEW_QUERY,
} from '@/constants/dashboardOverview'

const Q = OVERVIEW_QUERY
const INVALID = 422

function queryText(value) {
  return typeof value === 'string' ? value : ''
}

export function useExecutiveOverview() {
  const route = useRoute()
  const router = useRouter()
  const overview = ref(null)
  const status = ref('loading')
  const errorMessage = ref('')
  const fieldErrors = ref({})

  const period = computed(() => {
    const value = queryText(route.query[Q.PERIOD])
    return OVERVIEW_PRESETS.includes(value) ? value : DEFAULT_OVERVIEW_PERIOD
  })
  const customFrom = computed(() => queryText(route.query[Q.FROM]))
  const customTo = computed(() => queryText(route.query[Q.TO]))
  const isCustom = computed(() => period.value === OVERVIEW_PERIODS.CUSTOM)

  const request = computed(() =>
    isCustom.value
      ? { [Q.PERIOD]: period.value, [Q.FROM]: customFrom.value, [Q.TO]: customTo.value }
      : { [Q.PERIOD]: period.value },
  )

  async function load() {
    if (isCustom.value && !(customFrom.value && customTo.value)) return
    status.value = 'loading'
    errorMessage.value = ''
    fieldErrors.value = {}
    try {
      overview.value = (await dashboardService.getOverview(request.value)).data
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      fieldErrors.value =
        error?.normalized?.status === INVALID ? (error.normalized.fieldErrors ?? {}) : {}
      status.value = 'error'
    }
  }

  function replaceQuery(changes) {
    const query = { ...route.query, ...changes }
    Object.keys(changes).forEach((key) => changes[key] === undefined && delete query[key])
    return router.replace({ query })
  }

  function choosePreset(preset) {
    if (preset === OVERVIEW_PERIODS.CUSTOM) {
      return replaceQuery({ [Q.PERIOD]: preset })
    }
    return replaceQuery({ [Q.PERIOD]: preset, [Q.FROM]: undefined, [Q.TO]: undefined })
  }

  function applyCustom(from, to) {
    return replaceQuery({ [Q.PERIOD]: OVERVIEW_PERIODS.CUSTOM, [Q.FROM]: from, [Q.TO]: to })
  }

  watch(request, load, { deep: true })

  return {
    overview,
    status,
    errorMessage,
    fieldErrors,
    period,
    customFrom,
    customTo,
    isCustom,
    load,
    choosePreset,
    applyCustom,
  }
}
