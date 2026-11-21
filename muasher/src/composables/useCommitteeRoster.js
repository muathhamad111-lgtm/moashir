import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { committeeService } from '@/services/committeeService'
import { useAwardCycle } from '@/composables/useAwardCycle'
import { usePermissions } from '@/composables/usePermissions'
import { useToast } from '@/composables/useToast'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROSTER_OPEN_CYCLE_STATUSES } from '@/constants/committee'
import { errorMessageOf, failureState } from '@/utils/award/loadState'

export function useCommitteeRoster(cycleId) {
  const { t } = useI18n()
  const toast = useToast()
  const { can } = usePermissions()
  const { cycle, loadCycle } = useAwardCycle(cycleId)
  const status = ref('loading')
  const errorMessage = ref('')
  const members = ref([])

  const isOpen = computed(
    () => !cycle.value || ROSTER_OPEN_CYCLE_STATUSES.includes(cycle.value.status),
  )
  const canManage = computed(() => can(PERMISSIONS.AWARD_COMMITTEE_MANAGE) && isOpen.value)

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      members.value = (await committeeService.listMembers(cycleId.value)).data
      status.value = members.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = errorMessageOf(error)
      status.value = failureState(error)
    }
  }

  async function refresh(successKey, params = {}) {
    toast.success(t(successKey, params))
    await Promise.all([load(), loadCycle()])
  }

  onMounted(load)

  return { cycle, status, errorMessage, members, canManage, isOpen, load, refresh }
}
