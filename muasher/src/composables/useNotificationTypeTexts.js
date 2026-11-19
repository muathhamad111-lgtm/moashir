import { ref, toValue, watch } from 'vue'
import { notificationTextsService } from '@/services/notificationTextsService'

const FORBIDDEN = 403
const NOT_FOUND = 404

function statusOf(error) {
  const status = error?.normalized?.status
  if (status === FORBIDDEN) return 'forbidden'
  if (status === NOT_FOUND) return 'missing'
  return 'error'
}

export function useNotificationTypeTexts(code) {
  const type = ref(null)
  const status = ref('loading')

  async function load() {
    status.value = 'loading'
    try {
      const { data } = await notificationTextsService.show(toValue(code))
      type.value = data
      status.value = 'success'
    } catch (error) {
      status.value = statusOf(error)
    }
  }

  function replace(data) {
    type.value = data
  }

  watch(() => toValue(code), load, { immediate: true })

  return { type, status, load, replace }
}
