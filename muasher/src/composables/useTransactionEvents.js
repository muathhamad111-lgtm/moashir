import { onMounted, ref } from 'vue'
import { financeService } from '@/services/financeService'

// The operation's own event log (newest first, capped by the API).
export function useTransactionEvents(paymentId) {
  const status = ref('loading')
  const items = ref([])
  const hasMore = ref(false)
  const errorMessage = ref('')

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const envelope = await financeService.listTransactionEvents(paymentId)
      items.value = envelope.data ?? []
      hasMore.value = Boolean(envelope.meta?.hasMore)
      status.value = items.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  onMounted(load)

  return { status, items, hasMore, errorMessage, load }
}
