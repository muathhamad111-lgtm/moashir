import { shallowRef } from 'vue'
import { criteriaService } from '@/services/criteriaService'

// Main code → name in one version, so the change groups of main criteria that did not change
// themselves are headed by name. On failure the groups fall back to the code.
export function useCriteriaMainNames() {
  const mainNames = shallowRef(new Map())

  async function loadMainNames(versionId) {
    try {
      const envelope = await criteriaService.getTree(versionId)
      mainNames.value = new Map((envelope?.data?.mains ?? []).map((main) => [main.code, main.name]))
    } catch {
      mainNames.value = new Map()
    }
  }

  return { mainNames, loadMainNames }
}
