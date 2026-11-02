import { computed, ref } from 'vue'
import { useKpis } from '@/composables/useKpis'
import { KPI_CATEGORIES } from '@/constants/kpiCategories'

const PAGE_SIZE = 9

export const KPI_SORTS = ['order', 'name', 'newest', 'value-desc', 'value-asc']

export function useKpisCatalog({ pageSize = PAGE_SIZE } = {}) {
  const { kpis, status, load, localized } = useKpis()

  const category = ref('all')
  const statusFilter = ref('all')
  const search = ref('')
  const sort = ref('order')
  const visibleCount = ref(pageSize)

  const availableCategories = computed(() => {
    const present = new Set(kpis.value.map((kpi) => kpi.category))
    return KPI_CATEGORIES.filter((entry) => present.has(entry.key))
  })

  function matchesSearch(kpi) {
    const term = search.value.trim().toLowerCase()
    if (!term) return true
    const name = localized(kpi.name)?.toLowerCase() ?? ''
    return name.includes(term)
  }

  const filtered = computed(() => {
    let list = kpis.value.filter((kpi) => {
      if (category.value !== 'all' && kpi.category !== category.value) return false
      if (statusFilter.value !== 'all' && kpi.status !== statusFilter.value) return false
      return matchesSearch(kpi)
    })

    list = [...list]
    switch (sort.value) {
      case 'name':
        list.sort((a, b) => localized(a.name).localeCompare(localized(b.name)))
        break
      case 'newest':
        list.sort((a, b) => String(b.lastUpdate ?? '').localeCompare(String(a.lastUpdate ?? '')))
        break
      case 'value-desc':
        list.sort((a, b) => (Number(b.value) || 0) - (Number(a.value) || 0))
        break
      case 'value-asc':
        list.sort((a, b) => (Number(a.value) || 0) - (Number(b.value) || 0))
        break
      default:
        list.sort((a, b) => (a.order ?? 0) - (b.order ?? 0))
    }
    return list
  })

  const visible = computed(() => filtered.value.slice(0, visibleCount.value))
  const hasMore = computed(() => visibleCount.value < filtered.value.length)
  const isFiltering = computed(
    () =>
      category.value !== 'all' ||
      statusFilter.value !== 'all' ||
      search.value.trim() !== '' ||
      sort.value !== 'order',
  )

  function resetPaging() {
    visibleCount.value = pageSize
  }

  function setCategory(value) {
    category.value = value
    resetPaging()
  }

  function setStatus(value) {
    statusFilter.value = value
    resetPaging()
  }

  function setSearch(value) {
    search.value = value
    resetPaging()
  }

  function setSort(value) {
    sort.value = value
    resetPaging()
  }

  function showMore() {
    visibleCount.value += pageSize
  }

  return {
    kpis,
    status,
    load,
    localized,
    category,
    statusFilter,
    search,
    sort,
    availableCategories,
    filtered,
    visible,
    hasMore,
    isFiltering,
    setCategory,
    setStatus,
    setSearch,
    setSort,
    showMore,
  }
}
