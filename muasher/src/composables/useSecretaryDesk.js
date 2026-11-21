import { computed } from 'vue'
import { useFinanceList } from '@/composables/useFinanceList'
import { useAwardCycle } from '@/composables/useAwardCycle'
import { secretaryService } from '@/services/secretaryService'
import { COMMITTEE_LIMITS, DEFAULT_SECRETARY_SORT } from '@/constants/committee'

export function useSecretaryDesk(cycleId) {
  const { cycle } = useAwardCycle(cycleId)
  const list = useFinanceList({
    fetcher: (params) => secretaryService.list(cycleId.value, params),
    initialFilters: { status: '', regionId: '' },
    sort: DEFAULT_SECRETARY_SORT,
    perPage: COMMITTEE_LIMITS.PER_PAGE,
  })
  const counts = computed(() => list.meta.value.counts ?? null)

  return { ...list, cycle, counts }
}
