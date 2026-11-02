import { ref, shallowRef } from 'vue'
import { assessmentsService } from '@/services/assessmentsService'

const NOT_FOUND = 404

export function useAssessmentRecord(assessmentId) {
  const status = ref('loading')
  const errorMessage = ref('')
  const assessment = shallowRef(null)
  const result = shallowRef(null)

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const [record, outcome] = await Promise.all([
        assessmentsService.get(assessmentId.value),
        assessmentsService.getResult(assessmentId.value),
      ])
      assessment.value = record.data
      result.value = outcome.data
      status.value = 'ready'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = error?.normalized?.status === NOT_FOUND ? 'missing' : 'error'
    }
  }

  return { status, errorMessage, assessment, result, load }
}
