<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import FrozenBadge from '@/components/entities/FrozenBadge.vue'
import ResultPendingNotice from '@/components/assessment/ResultPendingNotice.vue'
import SelfResultSummary from '@/components/assessment/self/SelfResultSummary.vue'
import SelfResultBreakdown from '@/components/assessment/self/SelfResultBreakdown.vue'
import { useAssessmentResult } from '@/composables/useAssessmentResult'
import { SELF_STATUSES } from '@/constants/assessment'
import { ROUTE_NAMES } from '@/constants/routeNames'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const assessmentId = computed(() => Number(route.params.id))
const { status, errorMessage, result, load, init } = useAssessmentResult(assessmentId)

const isDraft = computed(() => result.value?.status === SELF_STATUSES.DRAFT)

function goTo(name) {
  router.push({ name })
}

onMounted(init)
</script>

<template>
  <section class="self-result">
    <div v-if="status === 'loading'" class="self-result__loading" aria-busy="true">
      <Skeleton variant="block" />
      <Skeleton variant="text" :count="5" />
    </div>
    <ErrorState v-else-if="status === 'error'" :message="errorMessage" @retry="load" />
    <EmptyState
      v-else-if="status === 'missing'"
      icon="fa-regular fa-clipboard"
      :title="t('assessment.result.missingTitle')"
      :message="t('assessment.result.missingMessage')"
    />

    <template v-else-if="result">
      <header class="self-result__header">
        <div>
          <p class="self-result__eyebrow">
            {{ t('assessment.self.result.eyebrow') }}
            <span class="u-ltr">{{ result.number }}</span>
          </p>
          <h1 class="self-result__title">{{ t('assessment.self.result.title') }}</h1>
        </div>
        <div class="self-result__badges">
          <StatusBadge :status="result.status" />
          <FrozenBadge v-if="result.isFrozen" />
        </div>
      </header>

      <ResultPendingNotice v-if="!result.available" :result="result">
        <BaseButton v-if="isDraft" variant="primary" icon="fa-solid fa-pen" @click="goTo(ROUTE_NAMES.ASSESSMENT_SELF)">
          {{ t('assessment.result.continueDraft') }}
        </BaseButton>
      </ResultPendingNotice>

      <template v-else>
        <SelfResultSummary :result="result" />
        <SelfResultBreakdown :result="result" />
      </template>

      <footer class="self-result__actions">
        <BaseButton variant="secondary" icon="fa-regular fa-clipboard" @click="goTo(ROUTE_NAMES.ASSESSMENT_HISTORY)">
          {{ t('assessment.self.result.viewHistory') }}
        </BaseButton>
        <BaseButton variant="primary" icon="fa-solid fa-gauge-high" @click="goTo(ROUTE_NAMES.APP_DASHBOARD)">
          {{ t('assessment.self.result.backToDashboard') }}
        </BaseButton>
      </footer>
    </template>
  </section>
</template>
