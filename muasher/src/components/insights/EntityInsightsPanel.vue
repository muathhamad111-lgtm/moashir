<script setup>
import { computed, onMounted, ref, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import Tabs from '@/components/ui/Tabs.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import InsightsResultSelector from './InsightsResultSelector.vue'
import InsightsSourceLine from './InsightsSourceLine.vue'
import InsightsEmptyState from './InsightsEmptyState.vue'
import InsightsNotices from './InsightsNotices.vue'
import StrengthsGapsView from './StrengthsGapsView.vue'
import MaturityView from './MaturityView.vue'
import BoardReportsView from './BoardReportsView.vue'
import { useInsightsOverview } from '@/composables/useInsightsOverview'
import { useLocalSelection } from '@/composables/useInsightsSelection'
import { INSIGHTS_TOOLS } from '@/constants/insights'

const props = defineProps({
  entityId: { type: Number, required: true },
})

const { t } = useI18n()
const { status, overview, results, load } = useInsightsOverview(toRef(props, 'entityId'))
const { selectedId, select } = useLocalSelection(overview)
const tab = ref(INSIGHTS_TOOLS.STRENGTHS)
const tabs = computed(() =>
  Object.values(INSIGHTS_TOOLS).map((value) => ({
    value,
    label: t(`portal.tools.items.${value}.title`),
  })),
)
const selectedResult = computed(
  () => results.value.find((result) => result.assessmentId === selectedId.value) ?? null,
)

onMounted(load)
</script>

<template>
  <section
    class="entity-insights"
    aria-labelledby="entity-insights-title"
    data-testid="entity-insights"
  >
    <header class="entity-insights__header">
      <div>
        <h2 id="entity-insights-title" class="entity-insights__title">
          {{ t('insights.staff.title') }}
        </h2>
        <p class="entity-insights__lead">{{ t('insights.staff.lead') }}</p>
      </div>
      <span class="entity-insights__readonly" data-testid="insights-read-only">
        <AppIcon name="eye" />
        {{ t('insights.staff.readOnly') }}
      </span>
    </header>
    <Skeleton v-if="status === 'loading'" variant="block" :count="2" />
    <ErrorState v-else-if="status === 'error'" :message="t('insights.loadError')" @retry="load" />
    <InsightsEmptyState
      v-else-if="!selectedId && overview?.emptyState"
      :empty-state="overview.emptyState"
    />
    <template v-else-if="selectedId">
      <div class="entity-insights__context">
        <InsightsResultSelector
          :results="results"
          :model-value="selectedId"
          @update:model-value="select"
        />
        <InsightsSourceLine v-if="selectedResult" :result="selectedResult" />
      </div>
      <InsightsNotices :overview="overview" />
      <Tabs v-model="tab" :tabs="tabs">
        <StrengthsGapsView
          v-if="tab === INSIGHTS_TOOLS.STRENGTHS"
          :assessment-id="selectedId"
          staff
        />
        <MaturityView
          v-else-if="tab === INSIGHTS_TOOLS.MATURITY"
          :assessment-id="selectedId"
          staff
        />
        <BoardReportsView v-else :assessment-id="selectedId" :entity-id="entityId" staff />
      </Tabs>
    </template>
  </section>
</template>
