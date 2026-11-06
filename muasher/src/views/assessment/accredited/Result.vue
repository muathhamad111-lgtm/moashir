<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, RouterLink } from 'vue-router'
import { useHead } from '@unhead/vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import EmptyState from '@/components/feedback/EmptyState.vue'
import ResultPendingNotice from '@/components/assessment/ResultPendingNotice.vue'
import SelfResultBreakdown from '@/components/assessment/self/SelfResultBreakdown.vue'
import AccreditedResultSummary from '@/components/accredited/AccreditedResultSummary.vue'
import AccreditedFeedbackList from '@/components/accredited/AccreditedFeedbackList.vue'
import { useAccreditedResult } from '@/composables/useAccreditedResult'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { RESULT_QUERY_KEY } from '@/constants/insights'
import { accessFor } from '@/constants/routeAccess'
import { usePermissions } from '@/composables/usePermissions'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  focus: { type: String, default: '' },
})

const { t } = useI18n()
const route = useRoute()
const assessmentId = computed(() => Number(route.params.id))
const { status, errorMessage, result, load } = useAccreditedResult(assessmentId)
const feedbackList = ref(null)
const { canAccess } = usePermissions()
const showsTools = computed(() => canAccess(accessFor(ROUTE_NAMES.PORTAL_TOOL_BOARD_REPORTS)))
const toolQuery = computed(() => ({ [RESULT_QUERY_KEY]: String(assessmentId.value) }))

useHead({ title: () => t('accredited.result.title') })

onMounted(async () => {
  await load()
  if (props.focus !== 'feedback' || !result.value?.available) return
  await nextTick()
  feedbackList.value?.focus()
})
</script>

<template>
  <section class="accredited-result">
    <header class="accredited-result__header result-band">
      <p class="accredited-result__eyebrow">{{ t('accredited.intro.eyebrow') }}</p>
      <h1 class="accredited-result__title">{{ t('accredited.result.title') }}</h1>
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
      v-else-if="status === 'missing'"
      icon="folder"
      :title="t('accredited.result.missing.title')"
      :message="t('accredited.result.missing.message')"
    />
    <ResultPendingNotice v-else-if="!result.available" :result="result">
      <RouterLink
        class="accredited-result__back"
        :to="{ name: ROUTE_NAMES.ASSESSMENT_ACCREDITED }"
        >{{ t('accredited.result.back') }}</RouterLink
      >
    </ResultPendingNotice>
    <template v-else>
      <AccreditedResultSummary :result="result" />
      <nav
        v-if="showsTools"
        class="accredited-result__tools"
        :aria-label="t('portal.tools.group')"
        data-testid="result-tools"
      >
        <RouterLink
          class="btn btn--secondary"
          :to="{ name: ROUTE_NAMES.PORTAL_TOOL_BOARD_REPORTS, query: toolQuery }"
        >
          <AppIcon name="docs" />
          {{ t('insights.links.boardReports') }}
        </RouterLink>
        <RouterLink
          class="btn btn--ghost"
          :to="{ name: ROUTE_NAMES.PORTAL_TOOL_STRENGTHS, query: toolQuery }"
        >
          <AppIcon name="target" />
          {{ t('insights.links.strengths') }}
        </RouterLink>
      </nav>
      <SelfResultBreakdown :result="result" />
      <AccreditedFeedbackList ref="feedbackList" :feedback="result.feedback" />
    </template>
  </section>
</template>
