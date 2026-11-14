import { computed, reactive } from 'vue'

function isSet(value) {
  return value !== '' && value !== null && value !== undefined
}

export function useFilterDraft(filters) {
  const initialFilters = Object.fromEntries(Object.keys(filters.filters).map((key) => [key, '']))
  const draft = reactive({ filters: { ...initialFilters }, sort: '' })

  const draftBinding = {
    filters: draft.filters,
    sort: computed(() => draft.sort),
    setFilter(key, value) {
      draft.filters[key] = value
    },
    setSort(value) {
      draft.sort = value
    },
  }

  const activeCount = computed(() => Object.values(filters.filters).filter(isSet).length)

  function load() {
    Object.assign(draft.filters, filters.filters)
    draft.sort = filters.sort.value
  }

  function resetDraft() {
    Object.assign(draft.filters, initialFilters)
    draft.sort = ''
  }

  function apply() {
    Object.entries(draft.filters).forEach(([key, value]) => filters.setFilter(key, value))
    filters.setSort(draft.sort)
  }

  function clear() {
    Object.keys(initialFilters).forEach((key) => filters.setFilter(key, ''))
  }

  return { draft: draftBinding, activeCount, load, resetDraft, apply, clear }
}
