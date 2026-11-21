<script setup>
import { computed, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import MaturityComparison from './MaturityComparison.vue'
import MaturityLegend from './MaturityLegend.vue'
import MaturityCriterionCard from './MaturityCriterionCard.vue'
import MaturityRemoved from './MaturityRemoved.vue'
import InsightsToolError from './InsightsToolError.vue'
import InsightsToolLinks from './InsightsToolLinks.vue'
import { useInsightsTool } from '@/composables/useInsightsTool'
import { insightsService } from '@/services/insightsService'
import { ROUTE_NAMES } from '@/constants/routeNames'

const props = defineProps({
  assessmentId: { type: Number, required: true },
  staff: { type: Boolean, default: false },
})

const emit = defineEmits(['loaded'])
const { t } = useI18n()
const tool = useInsightsTool(insightsService.maturity, toRef(props, 'assessmentId'))
const { status, data, reason } = tool

const links = computed(() =>
  props.staff
    ? []
    : [
        { key: 'strengths', to: { name: ROUTE_NAMES.PORTAL_TOOL_STRENGTHS }, keepQuery: true },
        {
          key: 'boardReports',
          to: { name: ROUTE_NAMES.PORTAL_TOOL_BOARD_REPORTS },
          keepQuery: true,
        },
      ],
)

watch(data, (value) => value && emit('loaded', value))
</script>

<template>
  <div class="insights-tool" data-testid="maturity" :aria-busy="status === 'loading'">
    <Skeleton v-if="status === 'loading' || status === 'idle'" variant="block" :count="3" />
    <InsightsToolError
      v-else-if="status !== 'ready'"
      :status="status"
      :reason="reason"
      @retry="tool.load"
    />
    <template v-else>
      <MaturityComparison :previous="data.previous" :versions-differ="data.versionsDiffer" />
      <MaturityLegend :rules="data.rules" />
      <div class="maturity-grid">
        <MaturityCriterionCard v-for="main in data.mains" :key="main.code" :main="main" />
      </div>
      <MaturityRemoved v-if="data.removedSincePrevious.length" :items="data.removedSincePrevious" />
      <InsightsToolLinks :links="links" :footnote="t('insights.maturity.footnote')" />
    </template>
  </div>
</template>
