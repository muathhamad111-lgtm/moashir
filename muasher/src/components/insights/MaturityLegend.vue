<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import MaturityBandChip from './MaturityBandChip.vue'
import InsightPercent from './InsightPercent.vue'
import { bandRanges } from '@/utils/insights'

const props = defineProps({
  rules: { type: Object, required: true },
})

const { t } = useI18n()
const ranges = computed(() => bandRanges(props.rules))
const LAST = 3
const DISPLAY_STEP = 0.01

function rangeKey(index) {
  if (index === 0) return 'below'
  return index === LAST ? 'from' : 'between'
}

function upper(range) {
  return (Number(range.to) - DISPLAY_STEP).toFixed(2)
}
</script>

<template>
  <div class="maturity-legend">
    <p class="maturity-legend__title">{{ t('insights.maturity.legend') }}</p>
    <ul class="maturity-legend__list">
      <li v-for="(range, index) in ranges" :key="range.band" class="maturity-legend__item">
        <MaturityBandChip :band="range.band" />
        <i18n-t :keypath="`insights.maturity.range.${rangeKey(index)}`" tag="span">
          <template #value>
            <InsightPercent :value="index === 0 ? range.to : range.from" />
          </template>
          <template #min><InsightPercent :value="range.from" /></template>
          <template #max><InsightPercent :value="upper(range)" /></template>
        </i18n-t>
      </li>
    </ul>
  </div>
</template>
