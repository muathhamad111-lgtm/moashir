<script setup>
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ResultPendingNotice from '@/components/assessment/ResultPendingNotice.vue'
import SelfResultBreakdown from '@/components/assessment/self/SelfResultBreakdown.vue'
import AccreditedResultSummary from '@/components/accredited/AccreditedResultSummary.vue'
import KitOfferCard from './KitOfferCard.vue'

defineProps({
  record: { type: Object, required: true },
  state: { type: Object, required: true },
})

const { t } = useI18n()
</script>

<template>
  <div class="request-result" data-testid="request-tab-result">
    <div
      v-if="state.status.value === 'loading'"
      class="accredited-result__loading"
      aria-busy="true"
    >
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="4" />
    </div>
    <ErrorState
      v-else-if="state.status.value === 'error'"
      :message="state.errorMessage.value"
      @retry="state.load"
    />
    <EmptyState
      v-else-if="state.status.value === 'missing'"
      icon="folder"
      :title="t('accredited.result.missing.title')"
      :message="t('accredited.result.missing.message')"
    />
    <ResultPendingNotice v-else-if="!state.result.value.available" :result="state.result.value" />
    <template v-else>
      <AccreditedResultSummary :result="state.result.value" />
      <SelfResultBreakdown :result="state.result.value" />
    </template>
    <KitOfferCard v-if="record.kit?.status === 'offered'" :record="record" />
  </div>
</template>
