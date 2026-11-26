<script setup>
import { computed, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Skeleton from '@/components/feedback/Skeleton.vue'
import StrengthsGapsSummary from './StrengthsGapsSummary.vue'
import StrengthsGapsBoard from './StrengthsGapsBoard.vue'
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
const tool = useInsightsTool(insightsService.strengthsGaps, toRef(props, 'assessmentId'))
const { status, data, reason } = tool

const links = computed(() => [
  {
    key: 'feedbackReport',
    to: { name: ROUTE_NAMES.REPORTS_FEEDBACK, params: { assessmentId: props.assessmentId } },
  },
  ...(props.staff
    ? []
    : [{ key: 'maturity', to: { name: ROUTE_NAMES.PORTAL_TOOL_MATURITY }, keepQuery: true }]),
])

watch(data, (value) => value && emit('loaded', value))
</script>

<template>
  <div class="insights-tool" data-testid="strengths-gaps" :aria-busy="status === 'loading'">
    <Skeleton v-if="status === 'loading' || status === 'idle'" variant="block" :count="3" />
    <InsightsToolError
      v-else-if="status !== 'ready'"
      :status="status"
      :reason="reason"
      @retry="tool.load"
    />
    <template v-else>
      <StrengthsGapsSummary :counts="data.counts" :mains="data.mains" />
      <StrengthsGapsBoard :items="data.items" :rules="data.rules" :counts="data.counts" />
      <InsightsToolLinks :links="links" :footnote="t('insights.strengths.footnote')" />
    </template>
  </div>
</template>
