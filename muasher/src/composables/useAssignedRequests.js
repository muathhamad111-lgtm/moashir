import { computed, ref, watch } from 'vue'
import { useTable } from '@/composables/useTable'
import { usePagination } from '@/composables/usePagination'
import { useRequestPathRoute } from '@/composables/useRequestPathRoute'
import { useRequestPathCountsStore } from '@/stores/requestPathCounts'
import { requestsService } from '@/services/requestsService'
import { ASSIGNED_TABS } from '@/constants/accredited'

export function useAssignedRequests(paths) {
  const counts = useRequestPathCountsStore()
  const { path } = useRequestPathRoute(paths)
  const tab = ref(ASSIGNED_TABS.NEW)
  const meta = ref({})
  const paginator = usePagination({ perPage: 10 })
  const table = useTable({
    fetcher: async (params) => {
      const envelope = await requestsService.listAssigned({
        ...params,
        'filter[path]': path.value,
        'filter[tab]': tab.value,
      })
      meta.value = envelope.meta ?? {}
      counts.applyAssigned(envelope.meta?.pathCounts)
      return envelope
    },
    pagination: paginator,
  })
  const pathCounts = computed(() => meta.value.pathCounts ?? counts.assigned)

  watch([tab, path], () => {
    paginator.setPage(1)
    table.load()
  })

  return { ...table, tab, path, pathCounts, paginator }
}
