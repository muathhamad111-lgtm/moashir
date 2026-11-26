<script setup>
import { useI18n } from 'vue-i18n'
import FieldTextarea from '@/components/form/FieldTextarea.vue'
import FieldRadio from '@/components/form/FieldRadio.vue'
import BaseDatePicker from '@/components/form/BaseDatePicker.vue'
import { ACCREDITED_LIMITS } from '@/constants/accredited'

// The second step of a completion request (design §B.3.3): the message to the entity, the deadline
// and what happens when it passes. The fields belong to the request's form.
defineProps({
  deadline: { type: String, default: '' },
  deadlineError: { type: String, default: '' },
  bounds: { type: Object, required: true },
  deadlineHint: { type: String, required: true },
  outcomeOptions: { type: Array, required: true },
})

const emit = defineEmits(['update:deadline'])
const { t } = useI18n()
</script>

<template>
  <section class="completion-dialog__details" aria-labelledby="completion-details-title">
    <h3 id="completion-details-title" class="completion-dialog__details-title">
      {{ t('review.completion.detailsTitle') }}
    </h3>
    <FieldTextarea
      class="completion-dialog__notes"
      name="notes"
      :label="t('review.completion.notes')"
      :hint="t('review.completion.notesHint', { max: ACCREDITED_LIMITS.COMPLETION_NOTES_MAX })"
      :rows="3"
      required
    />
    <BaseDatePicker
      :model-value="deadline"
      name="deadline"
      :label="t('review.completion.deadline')"
      :min="bounds.min"
      :max="bounds.max"
      :hint="deadlineHint"
      :error="deadlineError"
      required
      @update:model-value="emit('update:deadline', $event)"
    />
    <FieldRadio
      name="expiryOutcome"
      :label="t('review.completion.expiryOutcome')"
      :options="outcomeOptions"
    />
    <p class="completion-dialog__outcome-hint" role="note">
      {{ t('review.completion.expiryHint') }}
    </p>
  </section>
</template>
