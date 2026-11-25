<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import CompletionRequestCard from '@/components/accredited/CompletionRequestCard.vue'
import CriteriaRebindSummary from '@/components/assessment/self/CriteriaRebindSummary.vue'
import { useAuthStore } from '@/stores/auth'
import { ACCREDITED_STATUSES, REVIEW_MODES } from '@/constants/accredited'
import { isReviewFinished } from '@/utils/assessments/reviewStages'

const props = defineProps({
  review: { type: Object, required: true },
  conflictCode: { type: String, default: '' },
  canStart: { type: Boolean, default: false },
  starting: { type: Boolean, default: false },
  // The override mode has its own band, so the notice that opens it steps aside.
  overriding: { type: Boolean, default: false },
  // Sub-criteria waiting for this round's result after a completion (design §B.3.3).
  awaitingCodes: { type: Array, default: () => [] },
})

const emit = defineEmits(['refresh', 'override', 'start'])
const { t, locale } = useI18n()
const auth = useAuthStore()

const status = computed(() => props.review.assessment.status)
const awaitingList = computed(() =>
  new Intl.ListFormat(locale.value, { type: 'unit' }).format(props.awaitingCodes),
)
const isReadOnly = computed(() => props.review.mode === REVIEW_MODES.READ_ONLY)
// A finished review is read-only for everyone; the open-request message would mislead.
const readOnlyKey = computed(() =>
  isReviewFinished(props.review.assessment) ? 'review.finishedReadOnly' : 'review.readOnly',
)
const waitsForEntity = computed(() => status.value === ACCREDITED_STATUSES.NEEDS_COMPLETION)
const CLOSED_STATUSES = [
  ACCREDITED_STATUSES.COMPLETED,
  ACCREDITED_STATUSES.REFUNDED,
  ACCREDITED_STATUSES.DECLINED,
]
const showsCriteriaUpdate = computed(
  () => Boolean(props.review.criteriaUpdate) && !CLOSED_STATUSES.includes(status.value),
)
const mayOverride = computed(
  () => auth.isSuperAdmin && status.value === ACCREDITED_STATUSES.COMPLETED && !props.overriding,
)
</script>

<template>
  <div class="review-notices">
    <AssessmentNotice
      v-if="review.assessment.isFrozen"
      variant="frozen"
      :title="t('accredited.notices.frozen.title')"
      :message="t('review.frozenMessage')"
    />
    <AssessmentNotice
      v-if="conflictCode"
      variant="danger"
      live
      :title="t('review.conflict.title')"
      :message="t('review.conflict.message')"
    >
      <template #action>
        <BaseButton variant="secondary" size="sm" icon="refresh" @click="emit('refresh')">{{
          t('accredited.notices.refresh')
        }}</BaseButton>
      </template>
    </AssessmentNotice>
    <AssessmentNotice
      v-if="awaitingCodes.length && !isReadOnly"
      variant="warning"
      data-testid="review-awaiting-rescore"
      :title="
        t('review.rounds.awaitingTitle', { count: awaitingCodes.length }, awaitingCodes.length)
      "
      :message="t('review.rounds.awaitingMessage', { codes: awaitingList })"
    />
    <AssessmentNotice
      v-if="isReadOnly"
      variant="info"
      :title="t(`${readOnlyKey}Title`)"
      :message="t(`${readOnlyKey}Message`)"
    />
    <AssessmentNotice
      v-if="status === ACCREDITED_STATUSES.ASSIGNED && !isReadOnly"
      variant="info"
      :title="t('review.startTitle')"
      :message="t('review.startMessage')"
    >
      <template v-if="canStart" #action>
        <BaseButton
          variant="primary"
          size="sm"
          icon="play"
          data-testid="review-start"
          :loading="starting"
          @click="emit('start')"
          >{{ t('review.start') }}</BaseButton
        >
      </template>
    </AssessmentNotice>
    <CriteriaRebindSummary
      v-if="showsCriteriaUpdate"
      context="review"
      :assessment-id="review.assessment.id"
      :update="review.criteriaUpdate"
    />
    <AssessmentNotice
      v-if="waitsForEntity"
      variant="warning"
      :title="t('review.waitingTitle')"
      :message="t('review.waitingMessage')"
    >
      <CompletionRequestCard v-if="review.completionRequest" :request="review.completionRequest" />
    </AssessmentNotice>
    <AssessmentNotice
      v-if="mayOverride"
      variant="warning"
      :title="t('review.override.noticeTitle')"
      :message="t('review.override.noticeMessage')"
    >
      <template #action>
        <BaseButton variant="danger" size="sm" icon="pen" @click="emit('override')">{{
          t('review.override.open')
        }}</BaseButton>
      </template>
    </AssessmentNotice>
  </div>
</template>
