import { computed, watch } from 'vue'
import { useFinanceList } from '@/composables/useFinanceList'
import { useRequestPathRoute } from '@/composables/useRequestPathRoute'
import { useRequestPathCountsStore } from '@/stores/requestPathCounts'
import { requestsService } from '@/services/requestsService'
import { DEFAULT_QUEUE_SORT } from '@/constants/accredited'
import { overdueFromLink, statusForPath } from '@/utils/requestPaths'

const LINK_FILTERS = ['status', 'overdue']

export function useRequestQueue(paths) {
  const counts = useRequestPathCountsStore()
  const { route, path, linkStatus, linkOverdue, dropLinkFilter } = useRequestPathRoute(paths)
  const list = useFinanceList({
    fetcher: (params) => requestsService.listQueue(params),
    initialFilters: {
      path: path.value,
      cycleId: '',
      status: linkStatus.value,
      overdue: linkOverdue.value,
      assessorId: '',
    },
    sort: DEFAULT_QUEUE_SORT,
  })
  const stats = computed(() => list.meta.value.stats ?? null)
  const pathCounts = computed(() => list.meta.value.pathCounts ?? counts.queue)
  const linkQuery = computed(() =>
    Object.fromEntries(
      LINK_FILTERS.filter((key) => route.query[key]).map((key) => [key, route.query[key]]),
    ),
  )

  watch(path, (next) => {
    list.setFilter('path', next)
    list.setFilter('status', statusForPath(route.query.status, next))
    list.setFilter('cycleId', '')
    list.setFilter('assessorId', '')
  })
  watch(
    () => route.query.status,
    (raw) => raw && list.setFilter('status', statusForPath(raw, path.value)),
  )
  watch(
    () => route.query.overdue,
    (raw) => raw && list.setFilter('overdue', overdueFromLink(raw)),
  )
  watch(
    () => list.meta.value.pathCounts,
    (next) => next && counts.applyQueue(next),
  )

  function chooseFilter(key, value) {
    if (LINK_FILTERS.includes(key)) dropLinkFilter(key)
    list.setFilter(key, value)
  }

  return { ...list, path, stats, pathCounts, linkQuery, chooseFilter }
}
