<script setup>
import { useI18n } from 'vue-i18n'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import { ACCREDITED_LIMITS, FEEDBACK_FIELDS } from '@/constants/accredited'

defineProps({
  subId: { type: Number, required: true },
  fields: { type: Object, required: true },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update'])
const { t } = useI18n()

function errorOf(value) {
  return (value ?? '').length > ACCREDITED_LIMITS.FEEDBACK_MAX ? t('accredited.cv.tooLong', { max: ACCREDITED_LIMITS.FEEDBACK_MAX }) : ''
}
</script>

<template>
  <fieldset class="review-feedback">
    <legend class="review-feedback__legend">{{ t('review.feedbackLegend') }}</legend>
    <p class="review-feedback__hint">{{ t('review.feedbackOptional') }}</p>
    <BaseTextarea
      v-for="field in FEEDBACK_FIELDS"
      :key="field"
      :model-value="fields[field]"
      :name="`feedback-${subId}-${field}`"
      :label="t(`accredited.feedback.${field}`)"
      :rows="3"
      :error="errorOf(fields[field])"
      :disabled="disabled"
      @update:model-value="emit('update', { field, value: $event })"
    />
  </fieldset>
</template>
