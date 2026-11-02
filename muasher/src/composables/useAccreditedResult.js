import { ref, shallowRef } from 'vue'
import { accreditedService } from '@/services/accreditedService'

const NOT_FOUND = 404

export function useAccreditedResult(assessmentId) {
  const status = ref('loading')
  const errorMessage = ref('')
  const result = shallowRef(null)

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data } = await accreditedService.getResult(assessmentId.value)
      result.value = data
      status.value = 'ready'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = error?.normalized?.status === NOT_FOUND ? 'missing' : 'error'
    }
  }

  return { status, errorMessage, result, load }
}
