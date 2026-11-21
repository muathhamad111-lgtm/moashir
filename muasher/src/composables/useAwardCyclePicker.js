import { onMounted, ref } from 'vue'
import { awardCycleService } from '@/services/awardCycleService'
import { CYCLE_STATUSES } from '@/constants/advanced'
import { errorMessageOf } from '@/utils/award/loadState'

const LIST_SIZE = 50
const WITH_DRAFTS = ['committee']

export function useAwardCyclePicker(pickerKey) {
  const status = ref('loading')
  const errorMessage = ref('')
  const cycles = ref([])

  function visible(cycle) {
    return WITH_DRAFTS.includes(pickerKey) || cycle.status !== CYCLE_STATUSES.DRAFT
  }

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data } = await awardCycleService.list({ perPage: LIST_SIZE })
      cycles.value = data.filter(visible)
      status.value = cycles.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = errorMessageOf(error)
      status.value = 'error'
    }
  }

  onMounted(load)

  return { status, errorMessage, cycles, load }
}
