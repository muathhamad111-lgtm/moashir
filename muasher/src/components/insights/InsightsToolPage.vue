<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ErrorState from '@/components/feedback/ErrorState.vue'
import InsightsResultSelector from './InsightsResultSelector.vue'
import InsightsSourceLine from './InsightsSourceLine.vue'
import InsightsEmptyState from './InsightsEmptyState.vue'
import InsightsNotices from './InsightsNotices.vue'
import { useInsightsOverview } from '@/composables/useInsightsOverview'
import { useQuerySelection } from '@/composables/useInsightsSelection'

const props = defineProps({
  toolKey: { type: String, required: true },
})

const { t } = useI18n()
const { status, overview, results, load } = useInsightsOverview()
const { selectedId, select } = useQuerySelection(overview)
const selectedResult = computed(
  () => results.value.find((result) => result.assessmentId === selectedId.value) ?? null,
)
const emptyState = computed(() => overview.value?.emptyState ?? null)
const titleKey = computed(() => `portal.tools.items.${props.toolKey}.title`)

onMounted(load)
</script>

<template>
  <section class="insights-page" :data-tool="toolKey">
    <header class="b-band insights-band">
      <div class="b-band__inner insights-band__inner">
        <div class="insights-band__intro">
          <p class="insights-band__group">
            <AppIcon name="layers" />
            {{ t('portal.tools.group') }}
          </p>
          <h1 class="insights-band__title">{{ t(titleKey) }}</h1>
          <InsightsSourceLine v-if="selectedResult" :result="selectedResult" />
          <slot name="lines" />
        </div>
        <InsightsResultSelector
          v-if="results.length"
          class="insights-band__selector"
          :results="results"
          :model-value="selectedId"
          @update:model-value="select"
        />
      </div>
    </header>

    <div class="insights-page__body">
      <Skeleton v-if="status === 'loading'" variant="block" :count="3" />
      <ErrorState v-else-if="status === 'error'" :message="t('insights.loadError')" @retry="load" />
      <InsightsEmptyState v-else-if="emptyState && !selectedId" :empty-state="emptyState" />
      <template v-else-if="selectedId">
        <InsightsNotices :overview="overview" />
        <slot :assessment-id="selectedId" />
      </template>
    </div>
  </section>
</template>
