<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'

// The review's actions in one bar that stays at the bottom of the screen (design §B.3.1, AS-6): the
// way between criteria, then save and «إكمال التقييم» as the one filled button. The bar shows only
// buttons (operator, assessor journey #30); the save state is read out to screen readers. The
// completion request stays at the top of the page (operator, #26).
const props = defineProps({
  busy: { type: String, default: '' },
  dirty: { type: Boolean, default: false },
  canScore: { type: Boolean, default: false },
  isFirst: { type: Boolean, default: true },
  isLast: { type: Boolean, default: true },
  nextName: { type: String, default: '' },
  missing: { type: Number, default: 0 },
})

const emit = defineEmits(['save', 'complete', 'previous', 'next'])
const { t } = useI18n()

const isBusy = computed(() => Boolean(props.busy))
</script>

<template>
  <div
    class="review-actions review-actions--sticky"
    role="group"
    :aria-label="t('review.actionsLabel')"
  >
    <p class="review-actions__status" aria-live="polite">
      {{ dirty ? t('review.unsaved') : t('review.allSaved') }}
    </p>
    <div class="review-actions__nav">
      <BaseButton
        v-if="!isFirst"
        variant="ghost"
        icon="arrow-left"
        data-testid="step-previous"
        :disabled="isBusy"
        @click="emit('previous')"
      >
        {{ t('components.steps.previous') }}
      </BaseButton>
      <BaseButton
        v-if="!isLast"
        variant="secondary"
        icon-end="arrow-right"
        data-testid="step-next"
        :disabled="isBusy"
        @click="emit('next')"
      >
        <span class="review-actions__next" :title="nextName || undefined">{{
          nextName ? t('review.nextNamed', { name: nextName }) : t('components.steps.next')
        }}</span>
      </BaseButton>
    </div>
    <div v-if="canScore" class="review-actions__main">
      <BaseButton
        variant="secondary"
        icon="save"
        data-testid="review-save"
        :loading="busy === 'save'"
        :disabled="!dirty || isBusy"
        @click="emit('save')"
      >
        {{ t('review.save') }}
      </BaseButton>
      <BaseButton
        variant="primary"
        icon="flag-checkered"
        data-testid="review-complete"
        :loading="busy === 'complete'"
        :disabled="isBusy"
        @click="emit('complete')"
      >
        {{ missing ? t('review.completeMissing', { count: missing }) : t('review.complete') }}
      </BaseButton>
    </div>
  </div>
</template>
