import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { financeService } from '@/services/financeService'
import { EXPORT_POLL_DELAYS_MS, EXPORT_STATUSES } from '@/constants/finance'
import { errorStatusOf } from '@/utils/payments/financeErrors'

const NOT_FOUND = 404

export function useFinanceExport(exportId, { delays = EXPORT_POLL_DELAYS_MS } = {}) {
  const status = ref('loading')
  const record = ref(null)
  const errorMessage = ref('')
  let attempt = 0
  let timer = null

  const isQueued = computed(() => record.value?.status === EXPORT_STATUSES.QUEUED)
  const isReady = computed(() => record.value?.status === EXPORT_STATUSES.READY)

  function scheduleNext() {
    if (!isQueued.value) return
    timer = setTimeout(refresh, delays[Math.min(attempt, delays.length - 1)])
    attempt += 1
  }

  async function refresh() {
    try {
      record.value = (await financeService.getExport(exportId)).data
      status.value = 'success'
      scheduleNext()
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = errorStatusOf(error) === NOT_FOUND ? 'not-found' : 'error'
    }
  }

  function reload() {
    clearTimeout(timer)
    attempt = 0
    status.value = 'loading'
    return refresh()
  }

  function requestLink() {
    return financeService.requestExportDownload(exportId)
  }

  onMounted(refresh)
  onBeforeUnmount(() => clearTimeout(timer))

  return { status, record, errorMessage, isQueued, isReady, reload, requestLink }
}
