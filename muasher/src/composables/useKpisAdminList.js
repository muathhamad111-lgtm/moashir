import { ref } from 'vue'
import { kpisAdminService } from '@/services/kpisAdminService'
import { KPI_PAGE_SIZE } from '@/constants/kpis'
import { useTable } from './useTable'
import { useFilters } from './useFilters'
import { usePagination } from './usePagination'

export function useKpisAdminList() {
  const filters = useFilters({ published: '', featured: '', categoryId: '' })
  const paginator = usePagination({ perPage: KPI_PAGE_SIZE.admin })
  const stats = ref(null)

  const table = useTable({
    fetcher: async (params) => {
      const envelope = await kpisAdminService.list(params)
      stats.value = envelope.meta?.stats ?? null
      return envelope
    },
    filters,
    pagination: paginator,
  })

  return { filters, paginator, stats, ...table }
}
