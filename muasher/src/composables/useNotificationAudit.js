import { ref } from 'vue'
import { notificationsService } from '@/services/notificationsService'

export function useNotificationAudit() {
  const status = ref('idle')
  const errorMessage = ref('')
  const entries = ref([])
  const meta = ref({ code: '', link: '' })

  async function load(id) {
    status.value = 'loading'
    errorMessage.value = ''
    entries.value = []
    try {
      const envelope = await notificationsService.getAudit(id)
      entries.value = envelope.data?.entries ?? []
      meta.value = { code: envelope.data?.code ?? '', link: envelope.data?.link ?? '' }
      status.value = entries.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  function reset() {
    status.value = 'idle'
    errorMessage.value = ''
    entries.value = []
    meta.value = { code: '', link: '' }
  }

  return { status, errorMessage, entries, meta, load, reset }
}
