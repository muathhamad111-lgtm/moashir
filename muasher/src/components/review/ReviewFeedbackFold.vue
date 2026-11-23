<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import ReviewFeedbackFields from './ReviewFeedbackFields.vue'
import { ACCREDITED_LIMITS, FEEDBACK_FIELDS } from '@/constants/accredited'

// The three feedback fields of a sub-criterion at the end of its card (design §B.3.1, AS-4). They
// stay open while the review is editable (operator 2026-11-22) and fold when read-only; closed, the
// fold says how many are written.
const props = defineProps({
  subId: { type: Number, required: true },
  fields: { type: Object, required: true },
  noteLabel: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update'])
const { t } = useI18n()

const written = computed(
  () => FEEDBACK_FIELDS.filter((field) => (props.fields[field] ?? '').trim()).length,
)
// A field over its limit shows its error, so the fold opens rather than hiding it.
const tooLong = computed(() =>
  FEEDBACK_FIELDS.some(
    (field) => (props.fields[field] ?? '').length > ACCREDITED_LIMITS.FEEDBACK_MAX,
  ),
)
</script>

<template>
  <details class="review-feedback-fold" :open="!disabled || tooLong || undefined">
    <summary class="review-feedback-fold__summary">
      <AppIcon name="chevron-down" class="review-feedback-fold__chevron" />
      <span class="review-feedback-fold__title">{{ t('review.feedbackLegend') }}</span>
      <span class="review-feedback-fold__count">
        {{ t('review.feedbackWritten', { written, total: FEEDBACK_FIELDS.length }) }}
      </span>
    </summary>
    <ReviewFeedbackFields
      :sub-id="subId"
      :fields="fields"
      :note-label="noteLabel"
      :disabled="disabled"
      @update="emit('update', $event)"
    />
  </details>
</template>
