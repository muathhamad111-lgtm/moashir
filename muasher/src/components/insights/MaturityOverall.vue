<script setup>
import { useI18n } from 'vue-i18n'
import MaturityBandChip from './MaturityBandChip.vue'
import InsightPercent from './InsightPercent.vue'
import InsightsDelta from './InsightsDelta.vue'

defineProps({
  overall: { type: Object, required: true },
})

const { t } = useI18n()
</script>

<template>
  <p class="maturity-overall" data-testid="maturity-overall">
    <span class="maturity-overall__label">{{ t('insights.maturity.overall') }}</span>
    <MaturityBandChip :band="overall.band" />
    <InsightPercent class="maturity-overall__percent" :value="overall.percent" />
    <InsightsDelta :delta-pp="overall.deltaPp" on-band />
  </p>
</template>
