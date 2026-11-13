import { computed, reactive } from 'vue'
import { KPI_DEFAULT_SORT, KPI_SEARCH_MIN } from '@/constants/kpis'

export const ALL = 'all'

function emptyFilters() {
  return {
    category: ALL,
    status: ALL,
    year: ALL,
    type: ALL,
    owner: '',
    search: '',
    sort: KPI_DEFAULT_SORT,
  }
}

function searchable(text) {
  const term = text.trim()
  return term.length >= KPI_SEARCH_MIN ? term : null
}

export function useKpiFilters() {
  const filters = reactive(emptyFilters())

  const params = computed(() => {
    const entries = {
      search: searchable(filters.search),
      'filter[categoryId]': filters.category === ALL ? null : filters.category,
      'filter[status]': filters.status === ALL ? null : filters.status,
      'filter[measurementYear]': filters.year === ALL ? null : filters.year,
      'filter[kpiTypeId]': filters.type === ALL ? null : filters.type,
      'filter[owner]': searchable(filters.owner),
      sort: filters.sort === KPI_DEFAULT_SORT ? null : filters.sort,
    }
    return Object.fromEntries(Object.entries(entries).filter(([, value]) => value !== null))
  })

  const isFiltering = computed(() => Object.keys(params.value).length > 0)

  function set(key, value) {
    filters[key] = value
  }

  function reset() {
    Object.assign(filters, emptyFilters())
  }

  return { filters, params, isFiltering, set, reset }
}
