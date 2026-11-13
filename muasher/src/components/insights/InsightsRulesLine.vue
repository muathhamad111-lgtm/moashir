<script setup>
import { useI18n } from 'vue-i18n'
import InsightPercent from './InsightPercent.vue'

defineProps({
  kind: { type: String, required: true, validator: (value) => ['gaps', 'bands'].includes(value) },
  rules: { type: Object, required: true },
})

const { n } = useI18n()
</script>

<template>
  <i18n-t
    v-if="kind === 'gaps'"
    keypath="insights.rules.gaps"
    tag="p"
    class="insights-line"
    data-testid="insights-rules-line"
  >
    <template #critical><InsightPercent :value="rules.criticalBelow" /></template>
    <template #strength><InsightPercent :value="rules.strengthFrom" /></template>
    <template #version>
      <bdi dir="ltr">{{ n(rules.version) }}</bdi>
    </template>
  </i18n-t>
  <i18n-t
    v-else
    keypath="insights.rules.bands"
    tag="p"
    class="insights-line"
    data-testid="insights-rules-line"
  >
    <template #developing><InsightPercent :value="rules.developingFrom" /></template>
    <template #mature><InsightPercent :value="rules.matureFrom" /></template>
    <template #leading><InsightPercent :value="rules.leadingFrom" /></template>
    <template #version>
      <bdi dir="ltr">{{ n(rules.version) }}</bdi>
    </template>
  </i18n-t>
</template>
