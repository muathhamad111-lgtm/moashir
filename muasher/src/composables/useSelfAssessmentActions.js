import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { assessmentsService } from '@/services/assessmentsService'
import { useToast } from '@/composables/useToast'
import { logger } from '@/utils/logger'
import { payloadShape } from '@/utils/payloadShape'
import { SELF_ASSESSMENT_CODES as CODES } from '@/constants/assessment'

const CONFLICT_CODES = [CODES.TRANSITION_ILLEGAL, CODES.ENTITY_DELETED]

export function useSelfAssessmentActions({ formState, scoreSheet, onMissing, onSubmitted }) {
  const { t } = useI18n()
  const toast = useToast()
  const saving = ref(false)
  const submitting = ref(false)
  const confirmOpen = ref(false)
  const conflictCode = ref('')

  function assessmentId() {
    return formState.assessment.value.id
  }

  function sheetRequest(action) {
    const payload = { scores: scoreSheet.payload() }
    logger.breadcrumb('form', `assessments.${action}.submit`, { fields: payloadShape(payload) })
    return payload
  }

  function handleRejection(error) {
    const normalized = error?.normalized ?? {}
    if (CONFLICT_CODES.includes(normalized.code)) conflictCode.value = normalized.code
    if (normalized.code === CODES.ENTITY_DELETED) formState.patchAssessment({ isFrozen: true })
    if (normalized.code === CODES.CRITERIA_UPDATE_IN_PROGRESS) {
      formState.patchAssessment({ criteriaUpdateInProgress: true })
    }
    scoreSheet.applyServerErrors(normalized.fieldErrors)
    toast.error(normalized.message || t('errors.unexpected'))
  }

  async function save() {
    if (saving.value || submitting.value) return
    if (!(await scoreSheet.isValid())) return toast.error(t('assessment.self.toasts.fixErrors'))
    saving.value = true
    try {
      const { data } = await assessmentsService.saveDraft(assessmentId(), sheetRequest('saveDraft'))
      formState.applyScores(data)
      toast.success(t('assessment.self.toasts.draftSaved'))
    } catch (error) {
      handleRejection(error)
    } finally {
      saving.value = false
    }
  }

  async function requestSubmit() {
    if (saving.value || submitting.value) return
    if (!(await scoreSheet.isValid())) return toast.error(t('assessment.self.toasts.fixErrors'))
    const missing = scoreSheet.missing()
    if (missing.length) {
      await onMissing(missing)
      scoreSheet.markMissing(missing)
      return toast.error(t('assessment.self.toasts.incomplete', { count: missing.length }, missing.length))
    }
    confirmOpen.value = true
  }

  async function confirmSubmit() {
    if (submitting.value) return
    submitting.value = true
    try {
      const { data } = await assessmentsService.submit(assessmentId(), sheetRequest('submit'))
      confirmOpen.value = false
      toast.success(t('assessment.self.toasts.submitted'))
      onSubmitted(data)
    } catch (error) {
      confirmOpen.value = false
      handleRejection(error)
    } finally {
      submitting.value = false
    }
  }

  function clearConflict() {
    conflictCode.value = ''
  }

  return { saving, submitting, confirmOpen, conflictCode, save, requestSubmit, confirmSubmit, clearConflict }
}
