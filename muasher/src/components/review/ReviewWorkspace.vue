<script setup>
import { onMounted } from 'vue'
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
import ScoreOverrideReview from './ScoreOverrideReview.vue'
import ReviewOverrideBand from './ReviewOverrideBand.vue'
import CompleteReviewDialog from './CompleteReviewDialog.vue'
import ReviewSummaryCard from './ReviewSummaryCard.vue'
import ReviewCriteriaStrip from './ReviewCriteriaStrip.vue'
import ReviewCompletionAction from './ReviewCompletionAction.vue'
import ReviewNotesSaveAction from './ReviewNotesSaveAction.vue'
import { useReviewWorkspace } from '@/composables/useReviewWorkspace'

const props = defineProps({
  assessmentId: { type: Number, required: true },
})

// The page shows the request number beside its title.
const emit = defineEmits(['loaded'])

const { t } = useI18n()
const page = useReviewWorkspace(props, emit)
const { reviewState, workspace, dialogs, panelTop, steps, progress } = page
const { status, errorMessage, review, assessment, canScore, canStart } = reviewState
const { actions, override, notes } = workspace
const activeMain = steps.current
const { stations, missing, nextName } = progress

onMounted(reviewState.load)
</script>

<template>
  <div class="review-workspace" :class="{ 'is-overriding': override.active.value }">
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
      <ReviewCompletionAction
        v-if="reviewState.canRequestCompletion.value"
        :missing="page.savedMissing.value"
        @open="dialogs.completion = true"
      />
      <ReviewNotesSaveAction
        v-if="notes.canWrite.value && !canScore"
        :dirty="notes.dirty.value"
        :busy="actions.busy.value === 'notes'"
        @save="actions.saveNotes"
      />
      <ReviewNotices
        :review="review"
        :conflict-code="actions.conflictCode.value"
        :can-start="canStart"
        :overriding="override.active.value"
        :awaiting-codes="progress.awaitingCodes.value"
        :starting="actions.busy.value === 'start'"
        @start="actions.startReview"
        @refresh="page.refresh"
        @override="override.enter"
      />
      <ReviewSections :request-id="assessment.id">
        <template #assessment>
          <ReviewOverrideBand
            v-if="override.active.value"
            :override="override"
            @review="dialogs.override = true"
          />
          <ReviewCriteriaStrip
            :stations="stations"
            :active-id="activeMain?.id ?? null"
            @select="page.showStep(() => steps.goToMain($event))"
          />
          <div ref="panelTop" class="review-workspace__main" tabindex="-1">
            <ReviewMainPanel
              v-if="activeMain"
              :key="activeMain.id"
              :main="activeMain"
              :step="steps.position.value + 1"
              :total="steps.total.value"
              :workspace="workspace"
              @download="page.download"
            />
          </div>
          <ReviewSummaryCard
            v-if="page.offersCertificate.value && steps.isLast.value && !canScore"
            :summary="review.summary"
          />
          <ReviewActions
            :busy="actions.busy.value"
            :dirty="page.dirty.value"
            :can-score="canScore"
            :is-first="steps.isFirst.value"
            :is-last="steps.isLast.value"
            :next-name="nextName"
            :missing="missing"
            @previous="page.showStep(steps.previous)"
            @next="page.showStep(steps.next)"
            @save="actions.saveAll"
            @complete="dialogs.complete = true"
          />
        </template>
        <template #entity>
          <ReviewEntityCard :entity="review.entity" :context="review.context" />
        </template>
      </ReviewSections>
      <CompleteReviewDialog
        v-model="dialogs.complete"
        :message="page.completeMessage.value"
        :assessment-id="assessment.id"
        :offers-summary="page.offersCertificate.value"
        :summary="review.summary"
        :ask-reason="page.askReason"
        :requires-reason="page.auth.isSuperAdmin"
        @saved="reviewState.patch({ summary: $event })"
        @confirm="actions.complete"
      />
      <RequestCompletionDialog
        v-model="dialogs.completion"
        :active-main-id="activeMain?.id ?? null"
        :assessment-id="assessment.id"
        :criteria="review.criteria"
        :scores="review.scores"
        :review-notes="review.reviewNotes"
        :ask-reason="page.askReason"
        @done="reviewState.load({ silent: true })"
      />
      <ScoreOverrideReview
        v-model="dialogs.override"
        :override="override"
        :values="workspace.scoreSheet.sheet.values"
        :type-of="workspace.scoreSheet.typeOf"
      />
      <ReasonDialog
        v-model="page.reasonPrompt.open.value"
        :title="t('review.reasonTitle')"
        :submit-label="t('actions.confirm')"
        :action="page.reasonPrompt.provide"
        action-name="review.superAdminReason"
      />
    </template>
  </div>
</template>
