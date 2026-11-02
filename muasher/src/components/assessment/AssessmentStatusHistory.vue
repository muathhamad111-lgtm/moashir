<script setup>
import { ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import AssessmentTimeline from './AssessmentTimeline.vue'
import { useAssessmentStatusHistory } from '@/composables/useAssessmentStatusHistory'

const props = defineProps({
  assessmentId: { type: Number, required: true },
})

const { t } = useI18n()
const panelId = useId()
const expanded = ref(false)
const { status, history, errorMessage, load } = useAssessmentStatusHistory(props.assessmentId)

function toggle() {
  expanded.value = !expanded.value
  if (expanded.value && status.value === 'idle') load()
}
</script>

<template>
  <div class="status-history">
    <button type="button" class="status-history__toggle" :aria-expanded="expanded" :aria-controls="panelId" @click="toggle">
      <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
      {{ expanded ? t('assessment.timeline.hide') : t('assessment.timeline.show') }}
    </button>
    <div v-show="expanded" :id="panelId" class="status-history__panel">
      <Skeleton v-if="status === 'loading'" variant="text" :count="3" />
      <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
      <AssessmentTimeline v-else-if="status === 'ready'" :history="history" />
    </div>
  </div>
</template>
