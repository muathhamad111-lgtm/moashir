import { computed, ref, watch } from 'vue'
import { useTable } from '@/composables/useTable'
import { useFilters } from '@/composables/useFilters'
import { usePagination } from '@/composables/usePagination'
import { useRequestPathRoute } from '@/composables/useRequestPathRoute'
import { useRequestPathCountsStore } from '@/stores/requestPathCounts'
import { requestsService } from '@/services/requestsService'

const DEFAULT_SORT = 'due_at'

// The assessor's assigned requests on one path: all of them by default, narrowed to a status
// group by the filter, in the order of the column chosen in the table header.
export function useAssignedRequests(paths) {
  const counts = useRequestPathCountsStore()
  const { path } = useRequestPathRoute(paths)
  const filters = useFilters({ tab: '' })
  filters.setSort(DEFAULT_SORT)
  const meta = ref({})
  const paginator = usePagination({ perPage: 10 })
  const table = useTable({
    fetcher: async (params) => {
      const envelope = await requestsService.listAssigned({ ...params, 'filter[path]': path.value })
      meta.value = envelope.meta ?? {}
      counts.applyAssigned(envelope.meta?.pathCounts)
      return envelope
    },
    filters,
    pagination: paginator,
  })
  const pathCounts = computed(() => meta.value.pathCounts ?? counts.assigned)

  function setFilter(key, value) {
    paginator.setPage(1)
    filters.setFilter(key, value)
  }

  function setSort(value) {
    paginator.setPage(1)
    filters.setSort(value)
  }

  watch(path, () => {
    paginator.setPage(1)
    table.load()
  })

  return {
    ...table,
    filters: filters.filters,
    sort: filters.sort,
    setFilter,
    setSort,
    path,
    pathCounts,
    paginator,
  }
}
