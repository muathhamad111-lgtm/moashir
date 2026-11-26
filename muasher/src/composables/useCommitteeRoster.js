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
  // The API says whether this user manages THIS cycle's roster; an Award Secretary reads other cycles read-only.
  const managesCycle = ref(false)
  const canManage = computed(
    () => can(PERMISSIONS.AWARD_COMMITTEE_MANAGE) && managesCycle.value && isOpen.value,
  )

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const envelope = await committeeService.listMembers(cycleId.value)
      members.value = envelope.data
      managesCycle.value = envelope.meta?.canManage === true
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
