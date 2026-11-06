<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import { COMMITTEE_LIMITS } from '@/constants/committee'

const props = defineProps({
  busy: { type: String, default: '' },
  dirty: { type: Boolean, default: false },
  canScore: { type: Boolean, default: false },
  complete: { type: Boolean, default: false },
  generalNote: { type: String, default: '' },
})

const emit = defineEmits(['save', 'submit', 'update:generalNote'])
const { t } = useI18n()
const noteError = computed(() =>
  props.generalNote.length > COMMITTEE_LIMITS.NOTE_MAX
    ? t('award.common.maxChars', { max: COMMITTEE_LIMITS.NOTE_MAX })
    : '',
)
</script>

<template>
  <div class="review-actions committee-actions" role="group" :aria-label="t('review.actionsLabel')">
    <BaseTextarea
      :model-value="generalNote"
      name="committee-general-note"
      :label="t('award.evaluation.generalNote')"
      :hint="t('award.evaluation.generalNoteHint')"
      :rows="4"
      :error="noteError"
      :disabled="!canScore || Boolean(busy)"
      @update:model-value="emit('update:generalNote', $event)"
    />
    <p class="review-actions__status" aria-live="polite">
      {{ dirty ? t('review.unsaved') : t('review.allSaved') }}
    </p>
    <div v-if="canScore" class="review-actions__buttons">
      <BaseButton
        variant="secondary"
        icon="save"
        :loading="busy === 'save'"
        :disabled="!dirty || Boolean(busy)"
        @click="emit('save')"
        >{{ t('review.save') }}</BaseButton
      >
      <BaseButton
        variant="primary"
        icon="send"
        :loading="busy === 'submit'"
        :disabled="Boolean(busy)"
        @click="emit('submit')"
        >{{ t('award.evaluation.submit') }}</BaseButton
      >
    </div>
    <p v-if="canScore && !complete" class="review-actions__hint">
      {{ t('award.evaluation.submitHint') }}
    </p>
  </div>
</template>
