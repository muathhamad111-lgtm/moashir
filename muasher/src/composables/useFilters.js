import { reactive, ref } from 'vue'

export function useFilters(initialFilters = {}) {
  const search = ref('')
  const sort = ref('')
  const filters = reactive({ ...initialFilters })

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

  return { search, sort, filters, setFilter, setSort, reset, toParams }
}
