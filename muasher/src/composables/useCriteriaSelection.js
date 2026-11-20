import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ancestorsOf } from '@/utils/criteriaTree'
import { ROUTE_NAMES } from '@/constants/routeNames'

function withoutEmpty(query) {
  return Object.fromEntries(
    Object.entries(query).filter(([, value]) => value !== undefined && value !== null),
  )
}

export function useCriteriaSelection(index) {
  const route = useRoute()
  const router = useRouter()

  const requestedVersionId = computed(() =>
    route.query.version ? Number(route.query.version) : null,
  )
  const selectedKey = computed(() => (route.query.node ? String(route.query.node) : null))
  const requestedMainKey = computed(() => (route.query.main ? String(route.query.main) : null))
  const selected = computed(() => index.value.get(selectedKey.value) ?? null)
  const selectedPath = computed(() =>
    selected.value ? ancestorsOf(index.value, selected.value.key) : [],
  )

  function navigate(query) {
    return router.replace({
      name: ROUTE_NAMES.ADMIN_CRITERIA,
      query: withoutEmpty({ ...route.query, ...query }),
    })
  }

  // The board shows one main criterion; an open node (the editor sheet) belongs to it.
  const boardKey = computed(() => {
    if (selected.value) return selectedPath.value[0] ?? selected.value.key
    if (index.value.has(requestedMainKey.value)) return requestedMainKey.value
    return [...index.value.values()].find((item) => !item.parentKey)?.key ?? null
  })
  const board = computed(() => index.value.get(boardKey.value) ?? null)

  const select = (key) =>
    navigate({ node: key ?? undefined, main: key ? undefined : boardKey.value })
  const showMain = (key) => navigate({ main: key ?? undefined, node: undefined })
  const showVersion = (versionId) =>
    navigate({ version: versionId ?? undefined, node: undefined, main: undefined })

  return { requestedVersionId, selected, selectedPath, board, select, showMain, showVersion }
}
