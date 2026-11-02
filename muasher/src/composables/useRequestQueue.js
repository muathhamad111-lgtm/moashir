import { computed } from 'vue'
import { useFinanceList } from '@/composables/useFinanceList'
import { requestsService } from '@/services/requestsService'
import { DEFAULT_QUEUE_SORT } from '@/constants/accredited'

const QUEUE_FILTERS = { path: '', cycleId: '', status: '', overdue: '', assessorId: '' }

export function useRequestQueue() {
  const list = useFinanceList({
    fetcher: (params) => requestsService.listQueue(params),
    initialFilters: { ...QUEUE_FILTERS },
    sort: DEFAULT_QUEUE_SORT,
  })
  const stats = computed(() => list.meta.value.stats ?? null)
  return { ...list, stats }
}
