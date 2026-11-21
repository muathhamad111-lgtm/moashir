<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { versionLabelOf } from '@/utils/insights'

const props = defineProps({
  result: { type: Object, required: true },
})

const { t, locale } = useI18n()
const version = computed(
  () =>
    versionLabelOf(props.result.criteriaVersion, locale.value) ||
    t('insights.versionFallback', { number: props.result.criteriaVersion?.versionNumber ?? '' }),
)
</script>

<template>
  <i18n-t keypath="insights.sourceLine" tag="p" class="insights-line" data-testid="insights-source">
    <template #number>
      <bdi dir="ltr">{{ result.number }}</bdi>
    </template>
    <template #version>{{ version }}</template>
  </i18n-t>
</template>
