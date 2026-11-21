import { dashboardService } from '@/services/dashboardService'
import { usePeriodOverview } from './usePeriodOverview'

export function useExecutiveOverview() {
  return usePeriodOverview((query) => dashboardService.getOverview(query))
}
