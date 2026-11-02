import { computed, onMounted, shallowRef } from 'vue'
import { useFinanceList } from '@/composables/useFinanceList'
import { awardCycleService } from '@/services/awardCycleService'
import { filteringService } from '@/services/filteringService'
import { DEFAULT_FILTERING_SORT } from '@/constants/advanced'

export function useFilteringQueue(cycleId) {
  const cycle = shallowRef(null)
  const list = useFinanceList({
    fetcher: (params) => filteringService.list(cycleId.value, params),
    initialFilters: { status: '', regionId: '' },
    sort: DEFAULT_FILTERING_SORT,
  })
  const summary = computed(() => (list.meta.value.counts ? list.meta.value : null))

  async function loadCycle() {
    try {
      cycle.value = (await awardCycleService.get(cycleId.value)).data
    } catch {
      cycle.value = null
    }
  }

  onMounted(loadCycle)

  return { ...list, cycle, summary, loadCycle }
}
