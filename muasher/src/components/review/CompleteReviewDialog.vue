<script setup>
import { useI18n } from 'vue-i18n'
import DecisionDialog from '@/components/modal/DecisionDialog.vue'

defineProps({
  modelValue: { type: Boolean, required: true },
  message: { type: String, required: true },
  summaryMissing: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'confirm'])
const { t } = useI18n()

function confirm() {
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
    variant="warning"
    icon="flag-checkered"
    @update:model-value="emit('update:modelValue', $event)"
    @confirm="confirm"
  >
    <p v-if="summaryMissing" class="complete-review__hint" data-testid="summary-missing-hint">
      {{ t('review.summary.emptyHint') }}
    </p>
  </DecisionDialog>
</template>
