import { computed, ref, shallowRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { secretaryService } from '@/services/secretaryService'
import { usePermissions } from '@/composables/usePermissions'
import { useToast } from '@/composables/useToast'
import { useAwardCycle } from '@/composables/useAwardCycle'
import { PERMISSIONS } from '@/constants/permissionKeys'
import {
  ASSIGNMENT_STATUSES,
  COMMITTEE_REQUEST_STATUSES as C,
  DISCUSSION_STATUSES,
} from '@/constants/committee'
import { errorMessageOf, failureState } from '@/utils/award/loadState'

const COMMITTEE = Object.values(C)

export function useSecretaryRequest(cycleId, assessmentId) {
  const { t } = useI18n()
  const toast = useToast()
  const { can } = usePermissions()
  const { cycle } = useAwardCycle(cycleId)
  const status = ref('loading')
  const errorMessage = ref('')
  const detail = shallowRef(null)

  const decides = computed(() => can(PERMISSIONS.AWARD_SECRETARY_DECIDE))
  const state = computed(() => detail.value?.status ?? null)
  const openDiscussion = computed(
    () =>
      detail.value?.discussions.find(
        (discussion) => discussion.status === DISCUSSION_STATUSES.OPEN,
      ) ?? null,
  )
  const flags = computed(() => {
    const value = detail.value
    if (!value || !decides.value)
      return { recompute: false, approve: false, returnToCommittee: false, discussion: false }
    return {
      recompute: COMMITTEE.includes(state.value) && value.allSubmitted,
      approve:
        state.value === C.AWAITING_SECRETARY &&
        value.allSubmitted &&
        !value.pendingSubstitutionIds.length,
      returnToCommittee: [C.AWAITING_SECRETARY, C.DISCUSSION].includes(state.value),
      discussion: state.value === C.AWAITING_SECRETARY,
    }
  })

  function approvesSubstitution(evaluation) {
    return (
      decides.value &&
      evaluation.isSubstitute &&
      !evaluation.substituteApprovedAt &&
      !evaluation.voidedAt &&
      evaluation.status === ASSIGNMENT_STATUSES.COMPLETED
    )
  }

  async function load({ silent = false } = {}) {
    if (!silent) status.value = 'loading'
    errorMessage.value = ''
    try {
      detail.value = (await secretaryService.get(cycleId.value, assessmentId.value)).data
      status.value = 'ready'
    } catch (error) {
      if (silent) return
      errorMessage.value = errorMessageOf(error)
      status.value = failureState(error)
    }
  }

  function apply(data, successKey, params = {}) {
    detail.value = data
    toast.success(t(successKey, params))
  }

  return {
    cycle,
    status,
    errorMessage,
    detail,
    decides,
    flags,
    openDiscussion,
    approvesSubstitution,
    load,
    apply,
  }
}
