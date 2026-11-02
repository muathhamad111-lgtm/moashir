import { computed } from 'vue'
import { useFinanceList } from '@/composables/useFinanceList'
import { awardCycleService } from '@/services/awardCycleService'
import { DEFAULT_CYCLE_SORT } from '@/constants/advanced'

export function useCycleList() {
  const list = useFinanceList({
    fetcher: (params) => awardCycleService.list(params),
    initialFilters: { status: '' },
    sort: DEFAULT_CYCLE_SORT,
  })
  const stats = computed(() => list.meta.value.stats ?? null)
  return { ...list, stats }
}
