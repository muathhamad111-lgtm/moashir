<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useHead } from '@unhead/vue'
import InsightsToolPage from '@/components/insights/InsightsToolPage.vue'
import InsightsRulesLine from '@/components/insights/InsightsRulesLine.vue'
import MaturityOverall from '@/components/insights/MaturityOverall.vue'
import MaturityView from '@/components/insights/MaturityView.vue'
import { INSIGHTS_TOOLS } from '@/constants/insights'

const { t } = useI18n()
const report = ref(null)

useHead({ title: () => t('portal.tools.items.maturity.title') })
</script>

<template>
  <InsightsToolPage :tool-key="INSIGHTS_TOOLS.MATURITY">
    <template #lines>
      <template v-if="report">
        <InsightsRulesLine kind="bands" :rules="report.rules" />
        <MaturityOverall :overall="report.overall" />
      </template>
    </template>
    <template #default="{ assessmentId }">
      <MaturityView :assessment-id="assessmentId" @loaded="report = $event" />
    </template>
  </InsightsToolPage>
</template>
