import { computed, reactive } from 'vue'

function blankValues(source) {
  if (source.defaults) return { ...source.defaults }
  return Object.fromEntries(Object.keys(source.filters).map((key) => [key, '']))
}

export function useFilterDraft(source) {
  const blank = blankValues(source)
  const keys = Object.keys(blank)
  const defaultSort = source.defaultSort ?? ''
  const sortable = typeof source.setSort === 'function'
  const draft = reactive({ filters: { ...blank }, sort: defaultSort })

  const binding = {
    filters: draft.filters,
    sort: computed(() => draft.sort),
    setFilter(key, value) {
      draft.filters[key] = value
    },
    setSort(value) {
      draft.sort = value
    },
  }

  function isActive(key) {
    const value = source.filters[key]
    return value !== blank[key] && value !== '' && value !== null && value !== undefined
  }

  const activeCount = computed(() => keys.filter(isActive).length)

  function load() {
    keys.forEach((key) => (draft.filters[key] = source.filters[key] ?? blank[key]))
    draft.sort = sortable ? (source.sort?.value ?? defaultSort) : defaultSort
  }

  function resetDraft() {
    Object.assign(draft.filters, blank)
    draft.sort = defaultSort
  }

  function apply() {
    keys.forEach((key) => {
      if (draft.filters[key] !== source.filters[key]) source.setFilter(key, draft.filters[key])
    })
    if (sortable && draft.sort !== (source.sort?.value ?? defaultSort)) source.setSort(draft.sort)
  }

  function clear() {
    keys.filter(isActive).forEach((key) => source.setFilter(key, blank[key]))
  }

  return { draft: binding, activeCount, load, resetDraft, apply, clear }
}
