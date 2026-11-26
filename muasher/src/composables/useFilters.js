import { computed, reactive, ref } from 'vue'

// `defaults` names the values a list opens with on purpose (e.g. the pending requests): they are the
// list's default view, so the filter button counts only what the viewer changed from them.
export function useFilters(initialFilters = {}, { defaults = null } = {}) {
  const search = ref('')
  const sort = ref('')
  const filters = reactive({ ...initialFilters })
  const isAtDefaults = computed(() =>
    Object.keys(filters).every((key) => filters[key] === (defaults?.[key] ?? '')),
  )

  function setFilter(key, value) {
    filters[key] = value
  }

  function setSort(value) {
    sort.value = value
  }

  function reset() {
    search.value = ''
    sort.value = ''
    for (const key of Object.keys(filters)) filters[key] = initialFilters[key] ?? ''
  }

  function toParams() {
    const params = {}
    if (search.value) params.search = search.value
    if (sort.value) params.sort = sort.value
    for (const [key, value] of Object.entries(filters)) {
      if (value !== '' && value !== null && value !== undefined) params[`filter[${key}]`] = value
    }
    return params
  }

  return { search, sort, filters, defaults, isAtDefaults, setFilter, setSort, reset, toParams }
}
