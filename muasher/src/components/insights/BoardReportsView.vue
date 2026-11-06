<script setup>
import { computed, toRef, watch } from 'vue'
import Skeleton from '@/components/feedback/Skeleton.vue'
import ExecutiveSummary from './ExecutiveSummary.vue'
import BoardReportDownload from './BoardReportDownload.vue'
import ReportLibrary from './ReportLibrary.vue'
import InsightsToolError from './InsightsToolError.vue'
import InsightsToolLinks from './InsightsToolLinks.vue'
import { useInsightsTool } from '@/composables/useInsightsTool'
import { insightsService } from '@/services/insightsService'
import { ROUTE_NAMES } from '@/constants/routeNames'

const props = defineProps({
  assessmentId: { type: Number, required: true },
  entityId: { type: Number, default: null },
  staff: { type: Boolean, default: false },
})

const emit = defineEmits(['loaded'])
const tool = useInsightsTool(insightsService.executiveSummary, toRef(props, 'assessmentId'))
const { status, data, reason } = tool

const links = computed(() =>
  props.staff
    ? []
    : [
        { key: 'strengths', to: { name: ROUTE_NAMES.PORTAL_TOOL_STRENGTHS }, keepQuery: true },
        { key: 'maturity', to: { name: ROUTE_NAMES.PORTAL_TOOL_MATURITY }, keepQuery: true },
      ],
)

watch(data, (value) => value && emit('loaded', value))
</script>

<template>
  <div class="insights-tool" data-testid="board-reports" :aria-busy="status === 'loading'">
    <Skeleton v-if="status === 'loading' || status === 'idle'" variant="block" :count="2" />
    <InsightsToolError
      v-else-if="status !== 'ready'"
      :status="status"
      :reason="reason"
      @retry="tool.load"
    />
    <div v-else class="board-layout">
      <ExecutiveSummary class="board-layout__summary" :summary="data" />
      <BoardReportDownload class="board-layout__download" :assessment-id="assessmentId" />
    </div>
    <ReportLibrary :entity-id="entityId" :staff="staff" />
    <InsightsToolLinks v-if="links.length" :links="links" />
  </div>
</template>
