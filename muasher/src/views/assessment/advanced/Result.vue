<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import { useHead } from '@unhead/vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ResultPendingNotice from '@/components/assessment/ResultPendingNotice.vue'
import SelfResultBreakdown from '@/components/assessment/self/SelfResultBreakdown.vue'
import AccreditedResultSummary from '@/components/accredited/AccreditedResultSummary.vue'
import AccreditedFeedbackList from '@/components/accredited/AccreditedFeedbackList.vue'
import AwardOutcomeCard from '@/components/award/AwardOutcomeCard.vue'
import { useAdvancedResult } from '@/composables/useAdvancedResult'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const route = useRoute()
const assessmentId = computed(() => Number(route.params.id))
const { status, errorMessage, result, load } = useAdvancedResult(assessmentId)

useHead({ title: () => t('advanced.result.title') })

onMounted(load)
</script>

<template>
  <section class="accredited-result">
    <header class="accredited-result__header">
      <p class="accredited-result__eyebrow">{{ t('advanced.eyebrow') }}</p>
      <h1 class="accredited-result__title">{{ t('advanced.result.title') }}</h1>
      <p v-if="result" class="accredited-result__number">
        <bdi dir="ltr">{{ result.number }}</bdi>
      </p>
    </header>
    <div v-if="status === 'loading'" class="accredited-result__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="4" />
    </div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
    <EmptyState
      v-else-if="status === 'missing' || status === 'blocked'"
      icon="folder"
      :title="t('advanced.result.missingTitle')"
      :message="errorMessage || t('advanced.result.missingMessage')"
    />
    <ResultPendingNotice v-else-if="!result.available" :result="result">
      <RouterLink class="accredited-result__back" :to="{ name: ROUTE_NAMES.ASSESSMENT_ADVANCED }">{{
        t('advanced.result.back')
      }}</RouterLink>
    </ResultPendingNotice>
    <template v-else>
      <AwardOutcomeCard v-if="result.award" :award="result.award" />
      <AccreditedResultSummary :result="result" />
      <SelfResultBreakdown :result="result" />
      <AccreditedFeedbackList :feedback="result.feedback" />
    </template>
  </section>
</template>
