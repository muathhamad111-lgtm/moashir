<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { INSIGHTS_TOOLS } from '@/constants/insights'
import Tabs from '@/components/ui/Tabs.vue'
import MaturityView from '@/components/insights/MaturityView.vue'
import StrengthsGapsView from '@/components/insights/StrengthsGapsView.vue'
import BoardReportsView from '@/components/insights/BoardReportsView.vue'

defineProps({
  assessmentId: { type: Number, required: true },
})

const { t } = useI18n()
const tool = ref(INSIGHTS_TOOLS.STRENGTHS)
const tabs = computed(() =>
  [INSIGHTS_TOOLS.STRENGTHS, INSIGHTS_TOOLS.MATURITY, INSIGHTS_TOOLS.BOARD_REPORTS].map(
    (value) => ({ value, label: t(`portal.tools.items.${value}.title`) }),
  ),
)
</script>

<template>
  <div class="request-tools" data-testid="request-tab-tools">
    <Tabs v-model="tool" :tabs="tabs">
      <StrengthsGapsView v-if="tool === INSIGHTS_TOOLS.STRENGTHS" :assessment-id="assessmentId" />
      <MaturityView v-else-if="tool === INSIGHTS_TOOLS.MATURITY" :assessment-id="assessmentId" />
      <BoardReportsView v-else :assessment-id="assessmentId" />
    </Tabs>
  </div>
</template>
