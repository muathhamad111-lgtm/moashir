import { ref, watch } from 'vue'
import { useTable } from '@/composables/useTable'
import { usePagination } from '@/composables/usePagination'
import { requestsService } from '@/services/requestsService'
import { ASSIGNED_TABS } from '@/constants/accredited'

export function useAssignedRequests() {
  const tab = ref(ASSIGNED_TABS.NEW)
  const paginator = usePagination({ perPage: 10 })
  const table = useTable({
    fetcher: (params) => requestsService.listAssigned({ ...params, 'filter[tab]': tab.value }),
    pagination: paginator,
  })

  watch(tab, () => {
    paginator.setPage(1)
    table.load()
  })

  return { ...table, tab, paginator }
}
