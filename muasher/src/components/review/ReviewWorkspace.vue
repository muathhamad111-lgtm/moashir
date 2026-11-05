<script setup>
import { computed, onMounted, ref, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import CriteriaTree from '@/components/criteria/CriteriaTree.vue'
import ReasonDialog from '@/components/modal/ReasonDialog.vue'
import ReviewHeader from './ReviewHeader.vue'
import ReviewEntityCard from './ReviewEntityCard.vue'
import ReviewNotices from './ReviewNotices.vue'
import ReviewMainPanel from './ReviewMainPanel.vue'
import ReviewActions from './ReviewActions.vue'
import RequestCompletionDialog from './RequestCompletionDialog.vue'
import ScoreOverrideDialog from './ScoreOverrideDialog.vue'
import CompleteReviewDialog from './CompleteReviewDialog.vue'
import { useAssessorReview } from '@/composables/useAssessorReview'
import { useSelfScoreSheet } from '@/composables/useSelfScoreSheet'
import { useReviewFeedback } from '@/composables/useReviewFeedback'
import { useReviewActions } from '@/composables/useReviewActions'
import { useReasonPrompt } from '@/composables/useReasonPrompt'
import { useSignedDownload } from '@/composables/useSignedDownload'
import { useAuthStore } from '@/stores/auth'
import { evidenceService } from '@/services/evidenceService'
import { localizedText } from '@/utils/localizedText'

const props = defineProps({
  assessmentId: { type: Number, required: true },
})

const { t, locale } = useI18n()
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
const activeMainId = ref(null)
const completionOpen = ref(false)
const overrideOpen = ref(false)
const completeOpen = ref(false)
const ACCREDITED_PATH = 'accredited'

const mains = computed(() => review.value?.criteria?.mains ?? [])
const activeMain = computed(
  () => mains.value.find((main) => main.id === activeMainId.value) ?? mains.value[0] ?? null,
)
const dirty = computed(() => scoreSheet.dirty.value || feedback.dirty.value)
const fullyScored = computed(
  () => review.value && review.value.progress.scored === review.value.progress.required,
)
const treeNodes = computed(() =>
  mains.value.map((main) => ({
    id: main.id,
    label: `${main.code} ${localizedText(main.name, locale.value)}`,
    children: main.subs.map((sub) => ({
      id: sub.id,
      label: `${sub.code} ${localizedText(sub.name, locale.value)}`,
    })),
  })),
)

watch(review, (current, previous) => {
  if (!current || current === previous) return
  if (!scoreSheet.dirty.value) scoreSheet.resetFrom(current.scores)
  if (!feedback.dirty.value) feedback.resetFrom(current.feedback)
})

function selectNode({ node, parent }) {
  activeMainId.value = parent ? parent.id : node.id
}

function download(file) {
  return downloads.download(file.id, () =>
    evidenceService.requestDownload(props.assessmentId, file.id),
  )
}

const completeMessage = computed(() =>
  fullyScored.value && !scoreSheet.dirty.value
    ? t('review.confirmComplete.message')
    : t('review.confirmComplete.pendingMessage'),
)
const offersCertificate = computed(() => assessment.value?.path === ACCREDITED_PATH)

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
      <ReviewNotices
        :review="review"
        :conflict-code="actions.conflictCode.value"
        @refresh="refresh"
        @override="overrideOpen = true"
      />
      <ReviewEntityCard :entity="review.entity" :context="review.context" />
      <div class="review-workspace__layout">
        <aside class="review-workspace__nav" :aria-label="t('review.navLabel')">
          <CriteriaTree
            :nodes="treeNodes"
            :active-id="activeMain?.id ?? null"
            @select="selectNode"
          />
        </aside>
        <ReviewMainPanel
          v-if="activeMain"
          :key="activeMain.id"
          :main="activeMain"
          :workspace="workspace"
          @download="download"
        />
      </div>
      <ReviewActions
        :busy="actions.busy.value"
        :dirty="dirty"
        :can-score="canScore"
        :can-start="canStart"
        :can-request-completion="canRequestCompletion"
        :complete="fullyScored"
        @start="actions.startReview"
        @save="actions.saveAll"
        @request-completion="completionOpen = true"
        @complete="completeOpen = true"
      />
      <CompleteReviewDialog
        v-model="completeOpen"
        :message="completeMessage"
        :offers-certificate="offersCertificate"
        @confirm="actions.complete"
      />
      <RequestCompletionDialog
        v-model="completionOpen"
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
        :maximum="scoreSheet.maximum.value"
        @done="reviewState.load({ silent: true })"
      />
      <ReasonDialog
        v-model="reasonPrompt.open.value"
        :title="t('review.reasonTitle')"
        :message="t('review.reasonMessage')"
        :submit-label="t('actions.confirm')"
        :action="reasonPrompt.provide"
        action-name="review.superAdminReason"
      />
    </template>
  </div>
</template>
