<script setup>
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import AssessmentTimeline from '@/components/assessment/AssessmentTimeline.vue'
import AssignmentHistoryList from './AssignmentHistoryList.vue'

// The request's history: who held it, then every status change.
defineProps({
  detail: { type: Object, required: true },
})

const { t } = useI18n()
</script>

<template>
  <div class="request-log">
    <Card>
      <template #header>
        <h2 class="card__title-text">{{ t('requests.detail.historyTitle') }}</h2>
      </template>
      <AssignmentHistoryList :history="detail.assignmentHistory" />
    </Card>
    <Card>
      <template #header>
        <h2 class="card__title-text">{{ t('requests.detail.timelineTitle') }}</h2>
      </template>
      <AssessmentTimeline :history="detail.record.statusHistory" />
    </Card>
  </div>
</template>
