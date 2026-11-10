import { watch } from 'vue'
import { useFinanceList } from '@/composables/useFinanceList'
import { useRequestPathCountsStore } from '@/stores/requestPathCounts'
import { requestsService } from '@/services/requestsService'

export function usePreparingRequests(path) {
  const counts = useRequestPathCountsStore()
  const list = useFinanceList({
    fetcher: async (params) => {
      const envelope = await requestsService.listPreparing(params)
      counts.applyQueue(envelope.meta?.pathCounts)
      return envelope
    },
    initialFilters: { path: path.value },
  })

  watch(path, (next) => list.setFilter('path', next))

  return list
}
