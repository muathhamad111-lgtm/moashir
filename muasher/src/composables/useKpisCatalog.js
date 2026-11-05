import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { kpisService } from '@/services/kpisService'
import { KPI_PAGE_SIZE, KPI_SEARCH_DEBOUNCE_MS } from '@/constants/kpis'
import { localizedText } from '@/utils/localizedText'
import { useKpiFilters } from './useKpiFilters'
import { useKpiReferences } from './useKpiReferences'

export function useKpisCatalog({ pageSize = KPI_PAGE_SIZE.public } = {}) {
  const { locale } = useI18n()
  const filtering = useKpiFilters()
  const references = useKpiReferences()
  const kpis = ref([])
  const status = ref('loading')
  const loadingMore = ref(false)
  const paging = ref({ page: 1, lastPage: 1, total: 0 })
  let debounce = null
  let requestId = 0

  const hasMore = computed(() => paging.value.page < paging.value.lastPage)

  function localized(value) {
    return localizedText(value, locale.value)
  }

  async function fetchPage(page) {
    const envelope = await kpisService.list({ ...filtering.params.value, page, perPage: pageSize })
    paging.value = { page, lastPage: 1, total: 0, ...envelope.meta?.pagination }
    return envelope.data ?? []
  }

  async function load() {
    const current = ++requestId
    status.value = 'loading'
    try {
      const items = await fetchPage(1)
      if (current !== requestId) return
      kpis.value = items
      status.value = items.length || filtering.isFiltering.value ? 'success' : 'empty'
    } catch {
      if (current === requestId) status.value = 'error'
    }
  }

  async function showMore() {
    if (!hasMore.value || loadingMore.value || status.value !== 'success') return
    const current = requestId
    loadingMore.value = true
    try {
      const items = await fetchPage(paging.value.page + 1)
      if (current === requestId) kpis.value = [...kpis.value, ...items]
    } catch {
      if (current === requestId) status.value = 'error'
    } finally {
      loadingMore.value = false
    }
  }

  function loadNow() {
    clearTimeout(debounce)
    load()
  }

  function scheduleLoad() {
    clearTimeout(debounce)
    debounce = setTimeout(load, KPI_SEARCH_DEBOUNCE_MS)
  }

  const { filters } = filtering
  const params = filtering.params
  watch([() => filters.category, () => filters.status, () => filters.year, () => filters.type, () => filters.sort], loadNow)
  watch([() => params.value.search, () => params.value['filter[owner]']], scheduleLoad)
  onMounted(load)
  onBeforeUnmount(() => clearTimeout(debounce))

  return {
    kpis,
    status,
    loadingMore,
    paging,
    hasMore,
    localized,
    load,
    showMore,
    ...filtering,
    categories: references.categories,
    types: references.types,
  }
}
