<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseTextarea from '@/components/form/BaseTextarea.vue'
import CriterionStepButtons from '@/components/assessment/CriterionStepButtons.vue'
import { COMMITTEE_LIMITS } from '@/constants/committee'

const props = defineProps({
  busy: { type: String, default: '' },
  dirty: { type: Boolean, default: false },
  canScore: { type: Boolean, default: false },
  generalNote: { type: String, default: '' },
  isFirst: { type: Boolean, default: true },
  isLast: { type: Boolean, default: true },
})

const emit = defineEmits(['save', 'submit', 'update:generalNote', 'previous', 'next'])
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
      v-if="isLast"
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
    <CriterionStepButtons
      class="review-actions__buttons"
      :is-first="isFirst"
      :is-last="isLast"
      :disabled="Boolean(busy)"
      @previous="emit('previous')"
      @next="emit('next')"
    >
      <BaseButton
        v-if="canScore"
        variant="secondary"
        icon="save"
        :loading="busy === 'save'"
        :disabled="!dirty || Boolean(busy)"
        @click="emit('save')"
        >{{ t('review.save') }}</BaseButton
      >
      <template #last>
        <BaseButton
          v-if="canScore"
          variant="primary"
          icon="send"
          data-testid="committee-submit"
          :loading="busy === 'submit'"
          :disabled="Boolean(busy)"
          @click="emit('submit')"
          >{{ t('award.evaluation.submit') }}</BaseButton
        >
      </template>
    </CriterionStepButtons>
  </div>
</template>
