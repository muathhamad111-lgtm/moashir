<script setup>
import { useI18n } from 'vue-i18n'
import { thresholdText } from '@/utils/percentText'

defineProps({
  kind: { type: String, required: true, validator: (value) => ['gaps', 'bands'].includes(value) },
  rules: { type: Object, required: true },
})

const { t, n } = useI18n()
</script>

<template>
  <i18n-t
    v-if="kind === 'gaps'"
    keypath="insights.rules.gaps"
    tag="p"
    class="insights-line"
    data-testid="insights-rules-line"
  >
    <template #critical
      ><bdi class="insight-percent" dir="ltr">{{
        thresholdText(n, t, rules.criticalBelow)
      }}</bdi></template
    >
    <template #strength
      ><bdi class="insight-percent" dir="ltr">{{
        thresholdText(n, t, rules.strengthFrom)
      }}</bdi></template
    >
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
    <template #developing
      ><bdi class="insight-percent" dir="ltr">{{
        thresholdText(n, t, rules.developingFrom)
      }}</bdi></template
    >
    <template #mature
      ><bdi class="insight-percent" dir="ltr">{{
        thresholdText(n, t, rules.matureFrom)
      }}</bdi></template
    >
    <template #leading
      ><bdi class="insight-percent" dir="ltr">{{
        thresholdText(n, t, rules.leadingFrom)
      }}</bdi></template
    >
    <template #version>
      <bdi dir="ltr">{{ n(rules.version) }}</bdi>
    </template>
  </i18n-t>
</template>
