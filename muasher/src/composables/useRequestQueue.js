import { computed, ref, watch } from 'vue'
import { useFinanceList } from '@/composables/useFinanceList'
import { useRequestPathRoute } from '@/composables/useRequestPathRoute'
import { useRequestPathCountsStore } from '@/stores/requestPathCounts'
import { requestsService } from '@/services/requestsService'
import { DEFAULT_QUEUE_SORT, QUEUE_STAGES } from '@/constants/accredited'
import { overdueFromLink, statusForPath } from '@/utils/requestPaths'

const LINK_FILTERS = ['status', 'overdue']

function stageFilterOf(stage) {
  return stage === QUEUE_STAGES.ALL ? '' : stage
}

// The filters a user sets in the filter dialog. The path is not one: it comes from the
// sidebar entry, so it never counts as an applied filter. The stage is chosen in the dialog
// (operator note 2026-11-22) but lives in the address, so the stat cards and links share it.
const DIALOG_FILTER_DEFAULTS = Object.freeze({
  stage: '',
  cycleId: '',
  status: '',
  overdue: '',
  assessorId: '',
})

// A dialog choice: the stage goes to the address (the list follows it); a link filter stops following its link.
function filterChooser({ list, stage, dropLinkFilter }) {
  return (key, value) => {
    if (key === 'stage') {
      stage.value = value || QUEUE_STAGES.ALL
      return
    }
    if (LINK_FILTERS.includes(key)) dropLinkFilter(key)
    list.setFilter(key, value)
  }
}

// stage: the list's stage filter (a computed ref from useQueueStage); «all» sends no stage.
export function useRequestQueue(paths, stage = ref(QUEUE_STAGES.ALL)) {
  const counts = useRequestPathCountsStore()
  const { route, path, linkStatus, linkOverdue, dropLinkFilter } = useRequestPathRoute(paths)
  const list = useFinanceList({
    fetcher: (params) => requestsService.listQueue(params),
    initialFilters: {
      path: path.value,
      stage: stageFilterOf(stage.value),
      cycleId: '',
      status: linkStatus.value,
      overdue: linkOverdue.value,
      assessorId: '',
    },
    sort: DEFAULT_QUEUE_SORT,
  })
  const stats = computed(() => list.meta.value.stats ?? null)
  const pathCounts = computed(() => list.meta.value.pathCounts ?? counts.queue)

  watch(path, (next) => {
    list.setFilter('path', next)
    list.setFilter('status', statusForPath(route.query.status, next))
    list.setFilter('cycleId', '')
    list.setFilter('assessorId', '')
  })
  watch(stage, (next) => list.setFilter('stage', stageFilterOf(next)))
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

  return {
    ...list,
    path,
    stats,
    pathCounts,
    chooseFilter: filterChooser({ list, stage, dropLinkFilter }),
    filterDefaults: DIALOG_FILTER_DEFAULTS,
  }
}
