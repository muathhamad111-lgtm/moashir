import { computed, nextTick, reactive, ref, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useAssessorReview } from '@/composables/useAssessorReview'
import { useCompleteMessageKey } from '@/composables/useCertificateIssuanceMode'
import { useSelfScoreSheet } from '@/composables/useSelfScoreSheet'
import { useReviewFeedback } from '@/composables/useReviewFeedback'
import { useReviewNotes } from '@/composables/useReviewNotes'
import { useReviewActions } from '@/composables/useReviewActions'
import { useReasonPrompt } from '@/composables/useReasonPrompt'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { useCriterionSteps } from '@/composables/useCriterionSteps'
import { useReviewProgress } from '@/composables/useReviewProgress'
import { useReviewRounds } from '@/composables/useReviewRounds'
import { useScoreOverride } from '@/composables/useScoreOverride'
import { useAuthStore } from '@/stores/auth'
import { evidenceService } from '@/services/evidenceService'
import { ACCREDITED_STATUSES } from '@/constants/accredited'
import { REVIEW_OVERRIDE_QUERY } from '@/constants/review'

const ACCREDITED_PATH = 'accredited'

function workspaceParts(props) {
  const auth = useAuthStore()
  const reviewState = useAssessorReview(toRef(props, 'assessmentId'))
  const scoreSheet = useSelfScoreSheet({ form: reviewState.review, units: reviewState.units })
  const feedback = useReviewFeedback({ reviewState })
  const notes = useReviewNotes({ reviewState })
  const reasonPrompt = useReasonPrompt()
  const askReason = () => (auth.isSuperAdmin ? reasonPrompt.prompt() : Promise.resolve(null))
  const actions = useReviewActions({ reviewState, scoreSheet, feedback, notes, askReason })
  const downloads = useSignedDownload()
  const rounds = useReviewRounds(reviewState)
  const override = useScoreOverride({ reviewState, scoreSheet })
  const workspace = {
    reviewState,
    scoreSheet,
    feedback,
    notes,
    actions,
    downloads,
    rounds,
    override,
  }
  return { auth, reasonPrompt, askReason, workspace }
}

function useCompleteMessage(props, { assessment, savedMissing, scoreSheet }) {
  const { t } = useI18n()
  const offersCertificate = computed(() => assessment.value?.path === ACCREDITED_PATH)
  const messageKey = useCompleteMessageKey({
    assessmentId: toRef(props, 'assessmentId'),
    offersCertificate,
    hasPendingChanges: computed(() => savedMissing.value > 0 || scoreSheet.dirty.value),
  })
  return { offersCertificate, completeMessage: computed(() => t(messageKey.value)) }
}

// Opened from the request page's «تجاوز الدرجات», the review starts in the override mode once it
// has loaded, for a Super Admin on a published result only (the server checks it again).
function useOverrideFromQuery({ auth, workspace }) {
  const route = useRoute()
  if (route.query[REVIEW_OVERRIDE_QUERY] !== '1') return
  const stop = watch(workspace.reviewState.review, (review) => {
    if (!review) return
    stop()
    if (auth.isSuperAdmin && review.assessment.status === ACCREDITED_STATUSES.COMPLETED)
      workspace.override.enter()
  })
}

// Moving between criteria brings the new criterion's top into view and focus.
function useStepScroll() {
  const panelTop = ref(null)
  async function showStep(move) {
    move()
    await nextTick()
    panelTop.value?.scrollIntoView({ block: 'start' })
    panelTop.value?.focus()
  }
  return { panelTop, showStep }
}

// The state of the assessor's review page: the review, the score sheet and feedback, the actions,
// the completion rounds and the override mode (design §B.3.1–§B.3.5). The component only lays it out.
export function useReviewWorkspace(props, emit) {
  const parts = workspaceParts(props)
  useOverrideFromQuery(parts)
  const { reviewState, scoreSheet, feedback, notes, actions, downloads, rounds } = parts.workspace
  const review = reviewState.review
  const dialogs = reactive({ complete: false, completion: false, override: false })
  const mains = computed(() => review.value?.criteria?.mains ?? [])
  const steps = useCriterionSteps(mains)
  const units = reviewState.units
  const progress = useReviewProgress({ mains, units, scoreSheet, steps, rounds: rounds.rounds })
  const dirty = computed(() => scoreSheet.dirty.value || feedback.dirty.value || notes.dirty.value)
  // The server checks the saved scores before a completion request (UX-1), so the button does too.
  const savedMissing = computed(() =>
    review.value ? review.value.progress.required - review.value.progress.scored : 0,
  )

  watch(review, (current, previous) => {
    if (!current || current === previous) return
    emit('loaded', current.assessment.number)
    if (!scoreSheet.dirty.value) scoreSheet.resetFrom(current.scores)
    if (!feedback.dirty.value) feedback.resetFrom(current.feedback)
    if (!notes.dirty.value) notes.resetFrom()
  })

  const download = (file) =>
    downloads.download(file.id, () => evidenceService.requestDownload(props.assessmentId, file.id))

  async function refresh() {
    actions.clearConflict()
    await reviewState.load()
  }

  const message = useCompleteMessage(props, {
    assessment: reviewState.assessment,
    savedMissing,
    scoreSheet,
  })
  return {
    ...parts,
    ...message,
    ...useStepScroll(),
    reviewState,
    dialogs,
    steps,
    progress,
    dirty,
    savedMissing,
    download,
    refresh,
  }
}
