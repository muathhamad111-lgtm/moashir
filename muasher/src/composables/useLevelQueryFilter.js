import { watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { LEVEL_FILTER_VALUES, LEVEL_QUERY_KEY } from '@/constants/levels'

const FILTER_KEY = 'level'

export function levelFromQuery(value) {
  return typeof value === 'string' && LEVEL_FILTER_VALUES.includes(value) ? value : ''
}

export function useLevelQueryFilter() {
  const route = useRoute()
  const router = useRouter()
  const initialLevel = levelFromQuery(route.query[LEVEL_QUERY_KEY])

  function writeQuery(level) {
    if (levelFromQuery(route.query[LEVEL_QUERY_KEY]) === level) return
    const query = { ...route.query }
    if (level) query[LEVEL_QUERY_KEY] = level
    else delete query[LEVEL_QUERY_KEY]
    router.replace({ query })
  }

  function bind(filters) {
    watch(
      () => route.query[LEVEL_QUERY_KEY],
      (value) => filters.setFilter(FILTER_KEY, levelFromQuery(value)),
    )
    watch(() => filters.filters[FILTER_KEY], writeQuery)
  }

  return { initialLevel, bind }
}
