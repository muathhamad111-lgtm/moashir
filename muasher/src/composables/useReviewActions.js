import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { assessorReviewService } from '@/services/assessorReviewService'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { changedSheetPayload, isEmptyPayload } from '@/utils/assessments/scoreSheet'
import { ACCREDITED_CODES as CODES } from '@/constants/accredited'

const CONFLICT_CODES = [
  CODES.TRANSITION_ILLEGAL,
  CODES.ENTITY_DELETED,
  CODES.CRITERIA_UPDATE_IN_PROGRESS,
  CODES.NOT_ASSIGNEE,
]

export function useReviewActions({ reviewState, scoreSheet, feedback, askReason }) {
  const { t } = useI18n()
  const toast = useToast()
  const auth = useAuthStore()
  const busy = ref('')
  const conflictCode = ref('')

  function handle(error) {
    const normalized = error?.normalized ?? {}
    if (CONFLICT_CODES.includes(normalized.code)) conflictCode.value = normalized.code
    if (normalized.code === CODES.ENTITY_DELETED) reviewState.patchAssessment({ isFrozen: true })
    scoreSheet.applyServerErrors(normalized.fieldErrors)
    toast.error(normalized.message || t('errors.unexpected'))
  }

  async function withReason(run) {
    const extra = auth.isSuperAdmin ? { reason: await askReason() } : {}
    if (auth.isSuperAdmin && !extra.reason) return null
    return run(extra)
  }

  async function perform(action, run, successKey) {
    if (busy.value) return false
    busy.value = action
    try {
      const done = await withReason(run)
      if (done !== null && successKey) toast.success(t(successKey))
      return done !== null
    } catch (error) {
      handle(error)
      return false
    } finally {
      busy.value = ''
    }
  }

  async function sendScores(extra) {
    const payload = changedSheetPayload(
      scoreSheet.sheet.values,
      reviewState.units.value,
      reviewState.review.value.scores,
    )
    if (isEmptyPayload(payload)) return true
    const { data } = await assessorReviewService.saveScores(reviewState.assessment.value.id, {
      scores: payload,
      ...extra,
    })
    reviewState.patch(data)
    scoreSheet.resetFrom(data.scores)
    return true
  }

  async function saveAll() {
    if (!(await scoreSheet.isValid())) {
      toast.error(t('review.toasts.fixErrors'))
      return false
    }
    return perform(
      'save',
      async (extra) => {
        await sendScores(extra)
        await feedback.save(extra)
        return true
      },
      'review.toasts.saved',
    )
  }

  function startReview() {
    return perform(
      'start',
      async (extra) => {
        await assessorReviewService.startReview(reviewState.assessment.value.id, extra)
        await reviewState.load({ silent: true })
        return true
      },
      'review.toasts.started',
    )
  }

  function complete() {
    return perform(
      'complete',
      async (extra) => {
        await sendScores(extra)
        await feedback.save(extra)
        await assessorReviewService.complete(reviewState.assessment.value.id, extra)
        await reviewState.load({ silent: true })
        return true
      },
      'review.toasts.completed',
    )
  }

  function clearConflict() {
    conflictCode.value = ''
  }

  return { busy, conflictCode, saveAll, startReview, complete, handle, clearConflict }
}
