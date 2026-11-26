import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useProfileChangeSelection } from '@/composables/useProfileChangeSelection'
import { profileChangeRequestsService } from '@/services/profileChangeRequestsService'
import { PROFILE_CHANGE_STATUSES, REASON_LENGTH } from '@/constants/entityEnums'

function reasonRejection(error) {
  return error?.normalized?.fieldErrors?.reason ? error.normalized.message : ''
}

/** The reviewer's decision on one change request: the selected items, the reason and saving it. */
export function useProfileChangeDecision(requestRef, { onDecided }) {
  const { t } = useI18n()
  const toast = useToast()
  const reason = ref('')
  const reasonError = ref('')
  const saving = ref(false)
  const rows = computed(() => requestRef.value?.changes ?? [])
  const selection = useProfileChangeSelection(rows)
  const isPending = computed(() => requestRef.value?.status === PROFILE_CHANGE_STATUSES.PENDING)
  const needsReason = computed(() => selection.rejectedCount.value > 0)

  function validReason() {
    const tooShort = needsReason.value && reason.value.trim().length < REASON_LENGTH.MIN
    reasonError.value = tooShort ? t('entities.profileChanges.review.reasonRequired') : ''
    return !tooShort
  }

  function decision() {
    return {
      revision: requestRef.value.revision,
      approvedItems: selection.approvedItems.value,
      reason: needsReason.value ? reason.value.trim() : null,
    }
  }

  async function submit() {
    if (saving.value || !requestRef.value || !validReason()) return
    saving.value = true
    try {
      const { data } = await profileChangeRequestsService.decide(requestRef.value.id, decision())
      toast.success(t('entities.profileChanges.review.decided'))
      onDecided(data)
    } catch (error) {
      reasonError.value = reasonRejection(error)
      toast.error(error?.normalized?.message || t('errors.unexpected'))
    } finally {
      saving.value = false
    }
  }

  watch(
    requestRef,
    () => {
      selection.reset()
      reason.value = ''
      reasonError.value = ''
    },
    { immediate: true },
  )

  return { ...selection, rows, reason, reasonError, saving, isPending, needsReason, submit }
}
