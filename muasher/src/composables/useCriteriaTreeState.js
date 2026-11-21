import { ref } from 'vue'
import { useConsistencyCheck } from '@/composables/usePublishCriteria'
import { criteriaService } from '@/services/criteriaService'
import { CRITERIA_VERSION_STATUSES as STATUS } from '@/constants/criteria'

export function useCriteriaTreeState(version) {
  const consistency = useConsistencyCheck()
  const tree = ref(null)
  const status = ref('loading')
  const errorMessage = ref('')

  async function checkDraft() {
    consistency.clear()
    if (version.value?.status === STATUS.DRAFT) await consistency.check(version.value.id)
  }

  async function load() {
    if (!version.value) return
    status.value = 'loading'
    try {
      const { data } = await criteriaService.getTree(version.value.id)
      tree.value = data
      status.value = 'success'
      await checkDraft()
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  return { tree, status, errorMessage, problems: consistency.problems, load }
}
