import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

// The dashboard filters beside the period: region, path, entity type, sector and size. They live in
// the URL (shareable, kept on reload) and travel with every overview request.
export const BOARD_FILTER_FIELDS = Object.freeze(['region', 'path', 'type', 'sector', 'size'])

function textOf(value) {
  return typeof value === 'string' && value ? value : null
}

export function useBoardFilters() {
  const route = useRoute()
  const router = useRouter()

  const values = computed(() =>
    Object.fromEntries(BOARD_FILTER_FIELDS.map((field) => [field, textOf(route.query[field])])),
  )
  const request = computed(() =>
    Object.fromEntries(Object.entries(values.value).filter(([, value]) => value !== null)),
  )
  const activeCount = computed(() => Object.keys(request.value).length)

  function apply(chosen) {
    const query = { ...route.query }
    for (const field of BOARD_FILTER_FIELDS) query[field] = chosen[field] || undefined
    return router.replace({ query })
  }

  function clear() {
    return apply({})
  }

  return { values, request, activeCount, apply, clear }
}
