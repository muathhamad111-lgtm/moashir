import { ref, watch } from 'vue'
import { useOverviewPeriodQuery } from './useOverviewPeriodQuery'

const INVALID = 422

function fieldErrorsOf(error) {
  const normalized = error?.normalized
  return normalized?.status === INVALID ? (normalized.fieldErrors ?? {}) : {}
}

/**
 * A dashboard overview over the reporting period in the URL: the period picker's state, and the
 * overview the given loader returns for that period.
 */
export function usePeriodOverview(fetchOverview) {
  const query = useOverviewPeriodQuery()
  const overview = ref(null)
  const status = ref('loading')
  const errorMessage = ref('')
  const fieldErrors = ref({})

  async function load() {
    if (!query.isComplete.value) return
    status.value = 'loading'
    errorMessage.value = ''
    fieldErrors.value = {}
    try {
      overview.value = (await fetchOverview(query.request.value)).data
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      fieldErrors.value = fieldErrorsOf(error)
      status.value = 'error'
    }
  }

  watch(query.request, load, { deep: true })

  return {
    overview,
    status,
    errorMessage,
    fieldErrors,
    period: query.period,
    customFrom: query.customFrom,
    customTo: query.customTo,
    isCustom: query.isCustom,
    load,
    choosePreset: query.choosePreset,
    applyCustom: query.applyCustom,
  }
}
