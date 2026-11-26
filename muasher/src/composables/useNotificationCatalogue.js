import { ref } from 'vue'
import { notificationTypesService } from '@/services/notificationTypesService'

const FORBIDDEN = 403

export function useNotificationCatalogue() {
  const types = ref([])
  const status = ref('loading')
  const errorMessage = ref('')

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const envelope = await notificationTypesService.list()
      types.value = envelope.data
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = error?.normalized?.status === FORBIDDEN ? 'forbidden' : 'error'
    }
  }

  function replace(next) {
    types.value = next
  }

  return { types, status, errorMessage, load, replace }
}
