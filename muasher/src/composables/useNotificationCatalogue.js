import { reactive, ref, watch } from 'vue'
import { notificationTypesService } from '@/services/notificationTypesService'

const FORBIDDEN = 403

export function useNotificationCatalogue() {
  const filters = reactive({ module: '', category: '', audience: '' })
  const types = ref([])
  const total = ref(0)
  const status = ref('loading')
  const errorMessage = ref('')

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const envelope = await notificationTypesService.list({ ...filters })
      types.value = envelope.data
      total.value = envelope.meta?.total ?? envelope.data.length
      status.value = types.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = error?.normalized?.status === FORBIDDEN ? 'forbidden' : 'error'
    }
  }

  function resetFilters() {
    Object.assign(filters, { module: '', category: '', audience: '' })
  }

  watch(filters, load)

  return { filters, types, total, status, errorMessage, load, resetFilters }
}
