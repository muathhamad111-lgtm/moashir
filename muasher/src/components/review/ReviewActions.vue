<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'

defineProps({
  busy: { type: String, default: '' },
  dirty: { type: Boolean, default: false },
  canScore: { type: Boolean, default: false },
  canStart: { type: Boolean, default: false },
  canRequestCompletion: { type: Boolean, default: false },
  complete: { type: Boolean, default: false },
})

const emit = defineEmits(['start', 'save', 'requestCompletion', 'complete'])
const { t } = useI18n()
</script>

<template>
  <div class="review-actions" role="group" :aria-label="t('review.actionsLabel')">
    <p class="review-actions__status" aria-live="polite">{{ dirty ? t('review.unsaved') : t('review.allSaved') }}</p>
    <div class="review-actions__buttons">
      <BaseButton v-if="canStart" variant="primary" icon="fa-solid fa-play" :loading="busy === 'start'" @click="emit('start')">{{ t('review.start') }}</BaseButton>
      <template v-if="canScore">
        <BaseButton variant="secondary" icon="fa-regular fa-floppy-disk" :loading="busy === 'save'" :disabled="!dirty || Boolean(busy)" @click="emit('save')">{{ t('review.save') }}</BaseButton>
        <BaseButton v-if="canRequestCompletion" variant="secondary" icon="fa-solid fa-reply" :disabled="Boolean(busy)" @click="emit('requestCompletion')">{{ t('review.requestCompletion') }}</BaseButton>
        <BaseButton variant="primary" icon="fa-solid fa-flag-checkered" :loading="busy === 'complete'" :disabled="Boolean(busy)" @click="emit('complete')">{{ t('review.complete') }}</BaseButton>
      </template>
    </div>
    <p v-if="canScore && !complete" class="review-actions__hint">{{ t('review.completeHint') }}</p>
  </div>
</template>
