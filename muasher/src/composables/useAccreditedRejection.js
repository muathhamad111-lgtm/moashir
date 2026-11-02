import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { ACCREDITED_CODES as CODES } from '@/constants/accredited'
import { AWARD_CODES } from '@/constants/advanced'

const CONFLICT_CODES = [CODES.TRANSITION_ILLEGAL, CODES.ENTITY_DELETED, CODES.DEADLINE_PASSED, AWARD_CODES.SUBMISSION_CLOSED, AWARD_CODES.CONDITIONS_FAILED]
const LOCKING_CODES = [CODES.DEADLINE_PASSED, AWARD_CODES.SUBMISSION_CLOSED]

export function useAccreditedRejection(formState) {
  const { t } = useI18n()
  const toast = useToast()
  const conflictCode = ref('')

  function missingFrom(meta) {
    return { missingCv: meta.missingCv ?? [], missingAttachments: meta.missingAttachments ?? [] }
  }

  function handle(error) {
    const normalized = error?.normalized ?? {}
    if (CONFLICT_CODES.includes(normalized.code)) conflictCode.value = normalized.code
    if (normalized.code === CODES.ENTITY_DELETED) formState.patchAssessment({ isFrozen: true })
    if (LOCKING_CODES.includes(normalized.code)) formState.lock(normalized.code)
    if (normalized.code === CODES.CRITERIA_UPDATE_IN_PROGRESS) formState.patchAssessment({ criteriaUpdateInProgress: true })
    if (normalized.code === CODES.SUBMISSION_INCOMPLETE) formState.patch({ missing: missingFrom(normalized.meta ?? {}) })
    toast.error(normalized.message || t('errors.unexpected'))
  }

  function clear() {
    conflictCode.value = ''
  }

  return { conflictCode, handle, clear }
}
