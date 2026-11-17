<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import CriterionStepButtons from '@/components/assessment/CriterionStepButtons.vue'

defineProps({
  busy: { type: String, default: '' },
  dirty: { type: Boolean, default: false },
  canScore: { type: Boolean, default: false },
  canStart: { type: Boolean, default: false },
  canRequestCompletion: { type: Boolean, default: false },
  complete: { type: Boolean, default: false },
  isFirst: { type: Boolean, default: true },
  isLast: { type: Boolean, default: true },
})

const emit = defineEmits(['start', 'save', 'requestCompletion', 'complete', 'previous', 'next'])
const { t } = useI18n()
</script>

<template>
  <div class="review-actions" role="group" :aria-label="t('review.actionsLabel')">
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
        v-if="canStart"
        variant="primary"
        icon="play"
        data-testid="review-start"
        :loading="busy === 'start'"
        @click="emit('start')"
        >{{ t('review.start') }}</BaseButton
      >
      <template v-if="canScore">
        <BaseButton
          variant="secondary"
          icon="save"
          :loading="busy === 'save'"
          :disabled="!dirty || Boolean(busy)"
          @click="emit('save')"
          >{{ t('review.save') }}</BaseButton
        >
        <BaseButton
          v-if="canRequestCompletion"
          variant="secondary"
          icon="reply"
          :disabled="Boolean(busy)"
          @click="emit('requestCompletion')"
          >{{ t('review.requestCompletion') }}</BaseButton
        >
      </template>
      <template #last>
        <BaseButton
          v-if="canScore"
          variant="primary"
          icon="flag-checkered"
          data-testid="review-complete"
          :loading="busy === 'complete'"
          :disabled="Boolean(busy)"
          @click="emit('complete')"
          >{{ t('review.complete') }}</BaseButton
        >
      </template>
    </CriterionStepButtons>
    <p v-if="canScore && isLast && !complete" class="review-actions__hint">
      {{ t('review.completeHint') }}
    </p>
  </div>
</template>
