import { watch } from 'vue'
import { dashboardService } from '@/services/dashboardService'
import { usePeriodOverview } from './usePeriodOverview'
import { useBoardFilters } from './useBoardFilters'

// The executive overview over the period and the board filters in the URL.
export function useExecutiveOverview() {
  const filters = useBoardFilters()
  const state = usePeriodOverview((query) =>
    dashboardService.getOverview({ ...query, ...filters.request.value }),
  )
  watch(filters.request, state.load, { deep: true })
  return { ...state, filters }
}
