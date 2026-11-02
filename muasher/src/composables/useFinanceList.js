import { onBeforeUnmount, ref, watch } from 'vue'
import { useTable } from '@/composables/useTable'
import { useFilters } from '@/composables/useFilters'
import { usePagination } from '@/composables/usePagination'
import { SEARCH_DEBOUNCE_MS, SEARCH_MIN_LENGTH } from '@/constants/globalSearch'

export function useFinanceList({ fetcher, initialFilters = {}, perPage = 10, sort = '' }) {
  const filters = useFilters(initialFilters)
  const paginator = usePagination({ perPage })
  const searchInput = ref('')
  const meta = ref({})
  let timer = null

  if (sort) filters.setSort(sort)

  const table = useTable({
    fetcher: async (params) => {
      const envelope = await fetcher(params)
      meta.value = envelope.meta ?? {}
      return envelope
    },
    filters,
    pagination: paginator,
  })

  function applySearch(text) {
    const term = text.trim()
    const next = term.length >= SEARCH_MIN_LENGTH ? term : ''
    if (next === filters.search.value) return
    paginator.setPage(1)
    filters.search.value = next
  }

  watch(searchInput, (text) => {
    clearTimeout(timer)
    timer = setTimeout(() => applySearch(text), SEARCH_DEBOUNCE_MS)
  })

  function setFilter(key, value) {
    paginator.setPage(1)
    filters.setFilter(key, value)
  }

  function setSort(value) {
    paginator.setPage(1)
    filters.setSort(value)
  }

  onBeforeUnmount(() => clearTimeout(timer))

  return { ...table, filters, paginator, searchInput, meta, setFilter, setSort }
}
