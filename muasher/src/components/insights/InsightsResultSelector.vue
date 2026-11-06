<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppSelect from '@/components/form/AppSelect.vue'
import { isoDate } from '@/utils/datetime'
import { LEVEL_CERTIFICATE_STATUS as CERTIFICATE_ISSUED } from '@/constants/levels'

const props = defineProps({
  results: { type: Array, required: true },
  modelValue: { type: Number, default: null },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

function labelOf(result, index) {
  const certificate =
    result.certificate?.status === CERTIFICATE_ISSUED ? (result.certificate.number ?? null) : null
  const values = { number: result.number, date: isoDate(result.verifiedAt), certificate }
  const label = certificate
    ? t('insights.selector.optionWithCertificate', values)
    : t('insights.selector.option', values)
  return index === 0 ? `${label} · ${t('insights.selector.latest')}` : label
}

const options = computed(() =>
  props.results.map((result, index) => ({
    value: result.assessmentId,
    label: labelOf(result, index),
  })),
)
</script>

<template>
  <div class="insights-selector" data-testid="insights-selector">
    <AppSelect
      :model-value="modelValue"
      name="insights-result"
      :label="t('insights.selector.label')"
      :options="options"
      :loading="loading"
      @update:model-value="emit('update:modelValue', Number($event))"
    />
  </div>
</template>
