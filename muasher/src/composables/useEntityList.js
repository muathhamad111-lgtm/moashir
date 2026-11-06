import { computed, ref } from 'vue'
import { useTable } from '@/composables/useTable'
import { useFilters } from '@/composables/useFilters'
import { useLevelQueryFilter } from '@/composables/useLevelQueryFilter'
import { usePagination } from '@/composables/usePagination'
import { entitiesService } from '@/services/entitiesService'

const LIST_FILTERS = {
  status: '',
  regionId: '',
  entityType: '',
  developmentSector: '',
  orgSize: '',
}
const DELETED_LIST_FILTERS = { regionId: '', entityType: '' }
const DELETED_FILTER = { 'filter[trashed]': 'only' }
const PER_PAGE = 10

function levelAwareFilters() {
  const levelQuery = useLevelQueryFilter()
  const filters = useFilters({ ...LIST_FILTERS, level: levelQuery.initialLevel })
  levelQuery.bind(filters)
  return filters
}

export function useEntityList({ deleted = false } = {}) {
  const filters = deleted ? useFilters(DELETED_LIST_FILTERS) : levelAwareFilters()
  const paginator = usePagination({ perPage: PER_PAGE })
  const stats = ref(null)

  const table = useTable({
    fetcher: async (params) => {
      const envelope = await entitiesService.list(
        deleted ? { ...params, ...DELETED_FILTER } : params,
      )
      stats.value = envelope.meta?.stats ?? null
      return envelope
    },
    filters,
    pagination: paginator,
  })

  const exportParams = computed(() => filters.toParams())

  return { ...table, filters, paginator, stats, exportParams }
}
