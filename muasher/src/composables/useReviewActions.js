import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { assessorReviewService } from '@/services/assessorReviewService'
import { useToast } from '@/composables/useToast'
import { success } from '@/composables/useSuccess'
import { useAuthStore } from '@/stores/auth'
import {
  changedSheetPayload,
  isEmptyPayload,
  savedScoreOf,
  unitScorePayload,
} from '@/utils/assessments/scoreSheet'
import { ACCREDITED_CODES as CODES } from '@/constants/accredited'

const CONFLICT_CODES = [
  CODES.TRANSITION_ILLEGAL,
  CODES.ENTITY_DELETED,
  CODES.CRITERIA_UPDATE_IN_PROGRESS,
  CODES.NOT_ASSIGNEE,
]

export function useReviewActions({ reviewState, scoreSheet, feedback, notes, askReason }) {
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

  async function perform(action, run, announce) {
    if (busy.value) return false
    busy.value = action
    try {
      const done = await withReason(run)
      if (done !== null) announce()
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
        await notes.save(extra)
        return true
      },
      () => toast.success(t('review.toasts.saved')),
    )
  }

  // Staff who read the review without scoring it still save their internal notes (operator 2026-11-22).
  function saveNotes() {
    return perform(
      'notes',
      async (extra) => {
        await notes.save(extra)
        return true
      },
      () => toast.success(t('review.internalNotes.saved')),
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
      () => toast.success(t('review.toasts.started')),
    )
  }

  function complete() {
    return perform(
      'complete',
      async (extra) => {
        await sendScores(extra)
        await feedback.save(extra)
        await notes.save(extra)
        await assessorReviewService.complete(reviewState.assessment.value.id, extra)
        await reviewState.load({ silent: true })
        return true
      },
      () =>
        success.show({
          title: t('review.toasts.completed'),
          message: reviewState.assessment.value.number,
        }),
    )
  }

  // Keeps an item's result unchanged after a completion, recorded as the current round's result
  // (design §B.3.3): the server stores it as a new version tagged with the round.
  function confirmScore(unit) {
    const saved = savedScoreOf(reviewState.review.value.scores, unit)
    if (!saved) return Promise.resolve(false)
    return perform(
      'confirm',
      async (extra) => {
        const { data } = await assessorReviewService.saveScores(reviewState.assessment.value.id, {
          scores: unitScorePayload(unit, saved.rawValue),
          ...extra,
        })
        reviewState.patch(data)
        if (!scoreSheet.dirty.value) scoreSheet.resetFrom(data.scores)
        return true
      },
      () => toast.success(t('review.rounds.confirmed')),
    )
  }

  function clearConflict() {
    conflictCode.value = ''
  }

  return {
    busy,
    conflictCode,
    saveAll,
    saveNotes,
    startReview,
    complete,
    confirmScore,
    handle,
    clearConflict,
  }
}
