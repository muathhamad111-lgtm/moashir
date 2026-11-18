<script setup>
import { onMounted, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import AssessmentTimeline from '@/components/assessment/AssessmentTimeline.vue'
import CompletionRequestCard from '@/components/accredited/CompletionRequestCard.vue'
import RequestFacts from '@/components/requests/RequestFacts.vue'
import AssignmentHistoryList from '@/components/requests/AssignmentHistoryList.vue'
import { useRequestDetail } from '@/composables/useRequestDetail'

const props = defineProps({
  requestId: { type: Number, required: true },
})

const { t } = useI18n()
const { status, errorMessage, detail, load } = useRequestDetail(toRef(props, 'requestId'))

onMounted(load)
</script>

<template>
  <div class="review-request-tab">
    <div v-if="status === 'loading'" aria-busy="true">
      <Skeleton variant="block" /><Skeleton variant="text" :count="4" />
    </div>
    <ErrorState v-else-if="status !== 'ready'" :message="errorMessage" @retry="load" />
    <template v-else-if="detail">
      <Card>
        <template #header>
          <h2 class="card__title-text">{{ t('requests.detail.requestInfo') }}</h2>
        </template>
        <RequestFacts :detail="detail" />
      </Card>
      <Card v-for="completion in detail.completionRequests" :key="completion.id">
        <CompletionRequestCard :request="completion" show-status />
      </Card>
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
    </template>
  </div>
</template>
