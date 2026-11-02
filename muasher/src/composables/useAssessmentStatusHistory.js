import { ref } from 'vue'
import { assessmentsService } from '@/services/assessmentsService'

export function useAssessmentStatusHistory(assessmentId) {
  const status = ref('idle')
  const history = ref([])
  const errorMessage = ref('')

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      history.value = (await assessmentsService.get(assessmentId)).data.statusHistory
      status.value = 'ready'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  return { status, history, errorMessage, load }
}
