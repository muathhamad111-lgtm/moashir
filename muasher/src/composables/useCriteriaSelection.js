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

  const select = (key) => navigate({ node: key ?? undefined })
  const showVersion = (versionId) => navigate({ version: versionId ?? undefined, node: undefined })

  return { requestedVersionId, selected, selectedPath, select, showVersion }
}
