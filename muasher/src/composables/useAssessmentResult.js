import { ref, shallowRef } from 'vue'
import { assessmentsService } from '@/services/assessmentsService'
import { useAssessmentResultHandoffStore } from '@/stores/assessmentResultHandoff'

const NOT_FOUND = 404

export function useAssessmentResult(assessmentId) {
  const handoff = useAssessmentResultHandoffStore()
  const status = ref('loading')
  const errorMessage = ref('')
  const result = shallowRef(null)

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data } = await assessmentsService.getResult(assessmentId.value)
      result.value = data
      status.value = 'ready'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = error?.normalized?.status === NOT_FOUND ? 'missing' : 'error'
    }
  }

  function init() {
    const handed = handoff.consume(assessmentId.value)
    if (!handed) return load()
    result.value = handed
    status.value = 'ready'
    return Promise.resolve()
  }

  return { status, errorMessage, result, load, init }
}
