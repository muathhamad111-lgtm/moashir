import { computed } from 'vue'
import { useFinanceList } from '@/composables/useFinanceList'
import { committeeWorkService } from '@/services/committeeWorkService'
import { useAuthStore } from '@/stores/auth'
import { COMMITTEE_LIMITS } from '@/constants/committee'

export function useCommitteeAssignments() {
  const auth = useAuthStore()
  const list = useFinanceList({
    fetcher: (params) => committeeWorkService.listAssignments(params),
    initialFilters: { cycleId: '', status: '' },
    perPage: COMMITTEE_LIMITS.PER_PAGE,
  })
  const appointments = computed(() => auth.committeeAppointments)

  return { ...list, appointments }
}
