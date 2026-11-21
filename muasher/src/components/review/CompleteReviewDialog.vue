<script setup>
import { computed, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import DecisionDialog from '@/components/modal/DecisionDialog.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import { useAssessorSummary } from '@/composables/useAssessorSummary'
import { ASSESSOR_SUMMARY_MAX } from '@/constants/insights'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  message: { type: String, required: true },
  assessmentId: { type: Number, default: 0 },
  // The assessor summary belongs to the whole accredited assessment, so it is written here.
  offersSummary: { type: Boolean, default: false },
  summary: { type: Object, default: null },
  askReason: { type: Function, default: () => Promise.resolve(null) },
  requiresReason: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'confirm', 'saved'])
const { t, n } = useI18n()
const { text, error, saving, length, tooLong, dirty, save } = useAssessorSummary({
  assessmentId: toRef(props, 'assessmentId'),
  summary: toRef(props, 'summary'),
  askReason: () => props.askReason(),
  requiresReason: toRef(props, 'requiresReason'),
  onSaved: (data) => emit('saved', data),
})

const counter = computed(() =>
  t('review.summary.counter', { count: n(length.value), max: n(ASSESSOR_SUMMARY_MAX) }),
)
const fieldError = computed(() =>
  tooLong.value ? t('review.summary.errors.tooLong') : error.value,
)

// A changed summary is saved first; the assessment completes only if that save succeeded.
async function confirm() {
  if (props.offersSummary && dirty.value) {
    if (tooLong.value) return
    await save()
    if (error.value) return
  }
  emit('confirm')
  emit('update:modelValue', false)
}
</script>

<template>
  <DecisionDialog
    :model-value="modelValue"
    :title="t('review.confirmComplete.title')"
    :message="message"
    :confirm-label="t('review.confirmComplete.confirm')"
    :busy="saving"
    :size="offersSummary ? 'md' : 'sm'"
    variant="warning"
    icon="flag-checkered"
    @update:model-value="emit('update:modelValue', $event)"
    @confirm="confirm"
  >
    <div v-if="offersSummary" class="complete-review__summary" data-testid="review-summary">
      <BaseTextarea
        v-model="text"
        name="assessor-summary"
        :label="t('review.summary.label')"
        :rows="5"
        :hint="t('review.summary.help')"
        :error="fieldError"
      />
      <p class="review-summary__counter" :class="{ 'is-over': tooLong }" aria-live="polite">
        <bdi dir="ltr">{{ counter }}</bdi>
      </p>
    </div>
  </DecisionDialog>
</template>
