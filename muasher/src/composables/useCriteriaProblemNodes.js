import { shallowRef, watch } from 'vue'
import { criteriaService } from '@/services/criteriaService'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { CRITERIA_NODE_TYPES } from '@/constants/criteria'

function isMain(problem) {
  return problem.nodeType === CRITERIA_NODE_TYPES.MAIN
}

function keyOf(problem) {
  return `${isMain(problem) ? CRITERIA_NODE_TYPES.MAIN : CRITERIA_NODE_TYPES.SUB}-${problem.nodeId}`
}

async function fetchNode(problem) {
  if (isMain(problem)) return (await criteriaService.getMain(problem.nodeId)).data.main
  return (await criteriaService.getSub(problem.nodeId)).data.sub
}

export function useCriteriaProblemNodes(versionId, problems) {
  const nodes = shallowRef(new Map())

  async function load() {
    const pending = new Map(
      problems.value
        .filter((problem) => problem.nodeId)
        .map((problem) => [keyOf(problem), problem]),
    )
    const entries = await Promise.all(
      [...pending].map(async ([key, problem]) => [key, await fetchNode(problem)]),
    )
    nodes.value = new Map(entries)
  }

  watch(problems, () => load().catch(() => null), { immediate: true })

  function nodeOf(problem) {
    return problem.nodeId ? (nodes.value.get(keyOf(problem)) ?? null) : null
  }

  function linkFor(problem) {
    const query = { version: versionId.value }
    if (!problem.nodeId) return { name: ROUTE_NAMES.ADMIN_CRITERIA, query }
    return { name: ROUTE_NAMES.ADMIN_CRITERIA, query: { ...query, node: keyOf(problem) } }
  }

  return { nodeOf, linkFor }
}
