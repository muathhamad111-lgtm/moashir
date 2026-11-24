<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AssessmentNotice from '@/components/assessment/AssessmentNotice.vue'
import CycleBlockedRequests from './CycleBlockedRequests.vue'
import { useCycleStage } from '@/composables/useCycleStage'
import { AWARD_CODES } from '@/constants/advanced'

// What stands in the way of the cycle's next stage, under the header that holds the stage buttons
// (CycleStageButtons).
const props = defineProps({
  cycle: { type: Object, required: true },
  openBlock: { type: Object, default: null },
  blocked: { type: Object, default: null },
})

const { t } = useI18n()
const { manage, blockedOpen, blockedOpenMessage, needsAssessors, assigningBlocked } =
  useCycleStage(props)

const blockedRequests = computed(() =>
  props.blocked?.code === AWARD_CODES.REQUESTS_PENDING ? props.blocked.requests : [],
)
const anotherOpen = computed(() => props.blocked?.code === AWARD_CODES.ANOTHER_OPEN)
const anotherOpenForSubmission = computed(
  () => props.blocked?.code === AWARD_CODES.ANOTHER_OPEN_FOR_SUBMISSION,
)
const hasContent = computed(
  () =>
    !manage.value ||
    blockedOpen.value ||
    anotherOpen.value ||
    anotherOpenForSubmission.value ||
    needsAssessors.value ||
    assigningBlocked.value ||
    blockedRequests.value.length > 0,
)
</script>

<template>
  <section v-if="hasContent" class="cycle-actions">
    <AssessmentNotice
      v-if="blockedOpen"
      variant="warning"
      :title="t('cycles.actions.blockedOpen.title')"
      :message="blockedOpenMessage"
    />
    <AssessmentNotice
      v-if="anotherOpen"
      variant="danger"
      live
      :title="t('cycles.actions.anotherOpen.title')"
      :message="t('cycles.actions.anotherOpen.message')"
    />
    <AssessmentNotice
      v-if="anotherOpenForSubmission"
      variant="danger"
      live
      :title="t('cycles.actions.anotherOpen.staggeredTitle')"
      :message="t('cycles.actions.anotherOpen.staggeredMessage')"
    />
    <AssessmentNotice
      v-if="needsAssessors"
      variant="warning"
      :title="t('cycles.actions.needsAssessors.title')"
      :message="t('cycles.actions.needsAssessors.message')"
    />
    <AssessmentNotice
      v-if="assigningBlocked"
      variant="danger"
      :title="t('cycles.actions.assigningBlocked.title')"
      :message="t('cycles.actions.assigningBlocked.message')"
    />
    <CycleBlockedRequests v-if="blockedRequests.length" :requests="blockedRequests" />
    <p v-if="!manage" class="cycle-actions__note">{{ t('cycles.actions.readOnly') }}</p>
  </section>
</template>
