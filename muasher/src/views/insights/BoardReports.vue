<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import InsightsToolPage from '@/components/insights/InsightsToolPage.vue'
import InsightsRulesLine from '@/components/insights/InsightsRulesLine.vue'
import BoardReportsView from '@/components/insights/BoardReportsView.vue'
import { INSIGHTS_TOOLS } from '@/constants/insights'

const { t } = useI18n()
const rules = ref(null)

useHead({ title: () => t('portal.tools.items.boardReports.title') })
</script>

<template>
  <InsightsToolPage :tool-key="INSIGHTS_TOOLS.BOARD_REPORTS">
    <template #lines>
      <InsightsRulesLine v-if="rules" kind="gaps" :rules="rules" />
    </template>
    <template #default="{ assessmentId }">
      <BoardReportsView
        :assessment-id="assessmentId"
        @loaded="rules = $event.rules.gapThresholds"
      />
    </template>
  </InsightsToolPage>
</template>
