import { computed, ref, shallowRef } from 'vue'
import { awardCycleService } from '@/services/awardCycleService'
import { filteringService } from '@/services/filteringService'
import { usePermissions } from '@/composables/usePermissions'
import { useAuthStore } from '@/stores/auth'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ADVANCED_STATUSES, CYCLE_FILTERING_STATUSES } from '@/constants/advanced'

const NOT_FOUND = 404
const FORBIDDEN = 403

function failureState(error) {
  const status = error?.normalized?.status
  if (status === NOT_FOUND) return 'missing'
  if (status === FORBIDDEN) return 'blocked'
  return 'error'
}

export function useFilteringDetail(cycleId, assessmentId) {
  const auth = useAuthStore()
  const { can } = usePermissions()
  const status = ref('loading')
  const errorMessage = ref('')
  const detail = shallowRef(null)
  const cycle = shallowRef(null)

  const row = computed(() => detail.value?.row ?? null)
  const awaiting = computed(() => row.value?.status === ADVANCED_STATUSES.AWAITING_FILTERING && !row.value?.isFrozen)
  const windowOpen = computed(() => !cycle.value || CYCLE_FILTERING_STATUSES.includes(cycle.value.status))
  const mayDecide = computed(() => (auth.isSuperAdmin || can(PERMISSIONS.AWARD_FILTERING_DECIDE)) && awaiting.value)

  async function loadCycle() {
    try {
      cycle.value = (await awardCycleService.get(cycleId.value)).data
    } catch {
      cycle.value = null
    }
  }

  async function load({ silent = false } = {}) {
    if (!silent) status.value = 'loading'
    errorMessage.value = ''
    try {
      const [{ data }] = await Promise.all([filteringService.get(cycleId.value, assessmentId.value), loadCycle()])
      detail.value = data
      status.value = 'ready'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = failureState(error)
    }
  }

  return { status, errorMessage, detail, cycle, row, awaiting, windowOpen, mayDecide, load }
}
