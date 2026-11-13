import { ref, shallowRef } from 'vue'
import { assessmentsService } from '@/services/assessmentsService'
import { errorMessageOf, failureState } from '@/utils/award/loadState'

export function useAdvancedResult(assessmentId) {
  const status = ref('loading')
  const errorMessage = ref('')
  const result = shallowRef(null)

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      result.value = (await assessmentsService.getAdvancedResult(assessmentId.value)).data
      status.value = 'success'
    } catch (error) {
      errorMessage.value = errorMessageOf(error)
      status.value = failureState(error)
    }
  }

  return { status, errorMessage, result, load }
}
