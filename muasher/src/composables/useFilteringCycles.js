import { onMounted, ref } from 'vue'
import { awardCycleService } from '@/services/awardCycleService'
import { CYCLE_STATUSES } from '@/constants/advanced'

const LIST_SIZE = 50

export function useFilteringCycles() {
  const status = ref('loading')
  const errorMessage = ref('')
  const cycles = ref([])

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data } = await awardCycleService.list({ perPage: LIST_SIZE })
      cycles.value = data.filter((cycle) => cycle.status !== CYCLE_STATUSES.DRAFT)
      status.value = cycles.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  onMounted(load)

  return { status, errorMessage, cycles, load }
}
