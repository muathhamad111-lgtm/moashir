import { onMounted, reactive, ref, watch } from 'vue'
import { financeService } from '@/services/financeService'

function paramsOf(range) {
  return Object.fromEntries(Object.entries(range).filter(([, value]) => value))
}

export function useFinanceSummary() {
  const range = reactive({ from: '', to: '' })
  const status = ref('loading')
  const summary = ref(null)
  const errorMessage = ref('')

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      summary.value = (await financeService.getSummary(paramsOf(range))).data
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  onMounted(load)
  watch(range, load)

  return { range, status, summary, errorMessage, load }
}
