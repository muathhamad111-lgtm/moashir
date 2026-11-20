<script setup>
import { computed, nextTick, onMounted, ref, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import ReviewHeader from './ReviewHeader.vue'
import ReviewEntityCard from './ReviewEntityCard.vue'
import ReviewNotices from './ReviewNotices.vue'
import ReviewSections from './ReviewSections.vue'
import ReviewMainPanel from './ReviewMainPanel.vue'
import ReviewActions from './ReviewActions.vue'
import RequestCompletionDialog from './RequestCompletionDialog.vue'
import ScoreOverrideDialog from './ScoreOverrideDialog.vue'
import CompleteReviewDialog from './CompleteReviewDialog.vue'
import ReviewSummaryCard from './ReviewSummaryCard.vue'
import ReviewCriteriaStrip from './ReviewCriteriaStrip.vue'
import ReviewCompletionAction from './ReviewCompletionAction.vue'
import { useAssessorReview } from '@/composables/useAssessorReview'
import { useCompleteMessageKey } from '@/composables/useCertificateIssuanceMode'
import { useSelfScoreSheet } from '@/composables/useSelfScoreSheet'
import { useReviewFeedback } from '@/composables/useReviewFeedback'
import { useReviewActions } from '@/composables/useReviewActions'
import { useReasonPrompt } from '@/composables/useReasonPrompt'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { useAuthStore } from '@/stores/auth'
import { evidenceService } from '@/services/evidenceService'
import { useCriterionSteps } from '@/composables/useCriterionSteps'
import { useReviewProgress } from '@/composables/useReviewProgress'

const props = defineProps({
  assessmentId: { type: Number, required: true },
})

// The page shows the request number beside its title.
const emit = defineEmits(['loaded'])

const { t } = useI18n()
const auth = useAuthStore()
const reviewState = useAssessorReview(toRef(props, 'assessmentId'))
const {
  status,
  errorMessage,
  review,
  assessment,
  units,
  canScore,
  canStart,
  canRequestCompletion,
} = reviewState
const scoreSheet = useSelfScoreSheet({ form: review, units })
const feedback = useReviewFeedback({ reviewState })
const reasonPrompt = useReasonPrompt()
const askReason = () => (auth.isSuperAdmin ? reasonPrompt.prompt() : Promise.resolve(null))
const actions = useReviewActions({ reviewState, scoreSheet, feedback, askReason })
const downloads = useSignedDownload()
const workspace = { reviewState, scoreSheet, feedback, actions, downloads }
const panelTop = ref(null)
const completionOpen = ref(false)
const overrideOpen = ref(false)
const completeOpen = ref(false)
const ACCREDITED_PATH = 'accredited'

const mains = computed(() => review.value?.criteria?.mains ?? [])
const steps = useCriterionSteps(mains)
const activeMain = steps.current
const { stations, missing, nextName } = useReviewProgress({ mains, units, scoreSheet, steps })
const dirty = computed(() => scoreSheet.dirty.value || feedback.dirty.value)
const fullyScored = computed(
  () => review.value && review.value.progress.scored === review.value.progress.required,
)
watch(review, (current, previous) => {
  if (!current || current === previous) return
  emit('loaded', current.assessment.number)
  if (!scoreSheet.dirty.value) scoreSheet.resetFrom(current.scores)
  if (!feedback.dirty.value) feedback.resetFrom(current.feedback)
})

async function showStep(move) {
  move()
  await nextTick()
  panelTop.value?.scrollIntoView({ block: 'start' })
  panelTop.value?.focus()
}

function download(file) {
  return downloads.download(file.id, () =>
    evidenceService.requestDownload(props.assessmentId, file.id),
  )
}

const offersCertificate = computed(() => assessment.value?.path === ACCREDITED_PATH)
const messageKey = useCompleteMessageKey({
  assessmentId: toRef(props, 'assessmentId'),
  offersCertificate,
  hasPendingChanges: computed(() => !fullyScored.value || scoreSheet.dirty.value),
})
const completeMessage = computed(() => t(messageKey.value))

async function refresh() {
  actions.clearConflict()
  await reviewState.load()
}

onMounted(reviewState.load)
</script>

<template>
  <div class="review-workspace">
    <div v-if="status === 'loading'" class="review-workspace__loading" aria-busy="true">
      <Skeleton variant="block" /><Skeleton variant="text" :count="6" />
    </div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="reviewState.load" />
    <EmptyState
      v-else-if="status === 'missing' || status === 'blocked'"
      icon="lock"
      :title="t('review.unavailable.title')"
      :message="errorMessage || t('review.unavailable.message')"
    />
    <template v-else-if="review">
      <ReviewHeader :review="review" />
      <ReviewCompletionAction v-if="canRequestCompletion" @open="completionOpen = true" />
      <ReviewNotices
        :review="review"
        :conflict-code="actions.conflictCode.value"
        :can-start="canStart"
        :starting="actions.busy.value === 'start'"
        @start="actions.startReview"
        @refresh="refresh"
        @override="overrideOpen = true"
      />
      <ReviewSections :request-id="assessment.id">
        <template #assessment>
          <ReviewCriteriaStrip
            :stations="stations"
            :active-id="activeMain?.id ?? null"
            @select="showStep(() => steps.goToMain($event))"
          />
          <div ref="panelTop" class="review-workspace__main" tabindex="-1">
            <ReviewMainPanel
              v-if="activeMain"
              :key="activeMain.id"
              :main="activeMain"
              :step="steps.position.value + 1"
              :total="steps.total.value"
              :workspace="workspace"
              @download="download"
            />
          </div>
          <ReviewSummaryCard
            v-if="offersCertificate && steps.isLast.value && !canScore"
            :summary="review.summary"
          />
          <ReviewActions
            :busy="actions.busy.value"
            :dirty="dirty"
            :can-score="canScore"
            :is-first="steps.isFirst.value"
            :is-last="steps.isLast.value"
            :next-name="nextName"
            :missing="missing"
            @previous="showStep(steps.previous)"
            @next="showStep(steps.next)"
            @save="actions.saveAll"
            @complete="completeOpen = true"
          />
        </template>
        <template #entity>
          <ReviewEntityCard :entity="review.entity" :context="review.context" />
        </template>
      </ReviewSections>
      <CompleteReviewDialog
        v-model="completeOpen"
        :message="completeMessage"
        :assessment-id="assessment.id"
        :offers-summary="offersCertificate"
        :summary="review.summary"
        :ask-reason="askReason"
        :requires-reason="auth.isSuperAdmin"
        @saved="reviewState.patch({ summary: $event })"
        @confirm="actions.complete"
      />
      <RequestCompletionDialog
        v-model="completionOpen"
        :active-main-id="activeMain?.id ?? null"
        :assessment-id="assessment.id"
        :criteria="review.criteria"
        :ask-reason="askReason"
        @done="reviewState.load({ silent: true })"
      />
      <ScoreOverrideDialog
        v-model="overrideOpen"
        :assessment-id="assessment.id"
        :units="units"
        :scores="review.scores"
        :type-of="scoreSheet.typeOf"
        @done="reviewState.load({ silent: true })"
      />
      <ReasonDialog
        v-model="reasonPrompt.open.value"
        :title="t('review.reasonTitle')"
        :submit-label="t('actions.confirm')"
        :action="reasonPrompt.provide"
        action-name="review.superAdminReason"
      />
    </template>
  </div>
</template>
