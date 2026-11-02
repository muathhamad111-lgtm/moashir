import { ref, watch } from 'vue'
import { criteriaService } from '@/services/criteriaService'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { CRITERIA_NODE_TYPES } from '@/constants/criteria'

const MAIN_LOOKUP_SIZE = 100

function mainCodeOf(code) {
  return String(code ?? '').split('.')[0]
}

export function useCriteriaProblemLinks(versionId, problems) {
  const mainIdsByCode = ref(new Map())

  async function loadMains() {
    if (!versionId.value || !problems.value.length) return
    const envelope = await criteriaService.listMains({
      version: versionId.value,
      perPage: MAIN_LOOKUP_SIZE,
    })
    mainIdsByCode.value = new Map((envelope.data ?? []).map((main) => [main.code, main.id]))
  }

  watch([versionId, problems], () => loadMains().catch(() => null), { immediate: true })

  function linkFor(problem) {
    const indexRoute = { name: ROUTE_NAMES.ADMIN_CRITERIA, query: { version: versionId.value } }
    if (!problem.nodeId) return indexRoute
    if (problem.nodeType === CRITERIA_NODE_TYPES.MAIN) {
      return { name: ROUTE_NAMES.ADMIN_CRITERIA_MAIN, params: { mainId: problem.nodeId } }
    }
    const mainId = mainIdsByCode.value.get(mainCodeOf(problem.code))
    if (!mainId) return indexRoute
    return { name: ROUTE_NAMES.ADMIN_CRITERIA_SUB, params: { mainId, subId: problem.nodeId } }
  }

  return { linkFor }
}
