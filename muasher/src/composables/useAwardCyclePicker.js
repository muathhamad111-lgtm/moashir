import { computed, onMounted, ref } from 'vue'
import { awardCycleService } from '@/services/awardCycleService'
import { CYCLE_STATUSES } from '@/constants/advanced'
import { errorMessageOf } from '@/utils/award/loadState'

const LIST_SIZE = 50
const WITH_DRAFTS = ['committee']
const STORAGE_PREFIX = 'moashir.awardPicker.'

// The last cycle a user opened from a picker is a per-browser convenience; storage may be unavailable.
function readRemembered(pickerKey) {
  try {
    return Number(window.localStorage.getItem(STORAGE_PREFIX + pickerKey)) || null
  } catch {
    return null
  }
}

function writeRemembered(pickerKey, cycleId) {
  try {
    window.localStorage.setItem(STORAGE_PREFIX + pickerKey, String(cycleId))
  } catch {
    // Not remembered this time; the picker still works.
  }
}

export function useAwardCyclePicker(pickerKey) {
  const status = ref('loading')
  const errorMessage = ref('')
  const cycles = ref([])
  const remembered = ref(readRemembered(pickerKey))

  // A cycle still in progress; the picker opens it directly when it is the only one.
  const openCycles = computed(() =>
    cycles.value.filter((cycle) => cycle.status !== CYCLE_STATUSES.CLOSED),
  )

  function visible(cycle) {
    return WITH_DRAFTS.includes(pickerKey) || cycle.status !== CYCLE_STATUSES.DRAFT
  }

  function rememberedFirst(list) {
    return [...list].sort(
      (a, b) => Number(b.id === remembered.value) - Number(a.id === remembered.value),
    )
  }

  function remember(cycleId) {
    remembered.value = cycleId
    writeRemembered(pickerKey, cycleId)
  }

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data } = await awardCycleService.list({ perPage: LIST_SIZE })
      cycles.value = rememberedFirst(data.filter(visible))
      status.value = cycles.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = errorMessageOf(error)
      status.value = 'error'
    }
  }

  onMounted(load)

  return { status, errorMessage, cycles, openCycles, remembered, remember, load }
}
