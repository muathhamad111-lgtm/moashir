<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  title: { type: String, required: true },
  titleId: { type: String, required: true },
  canShowPrevious: { type: Boolean, default: true },
  canShowNext: { type: Boolean, default: true },
})

const emit = defineEmits(['previous', 'next'])

const { t } = useI18n()
</script>

<template>
  <div class="date-calendar__header">
    <button
      type="button"
      class="date-calendar__nav date-calendar__nav--previous"
      :aria-label="t('components.datePicker.previousMonth')"
      :title="t('components.datePicker.previousMonth')"
      :disabled="!canShowPrevious"
      @click="emit('previous')"
    >
      <AppIcon name="chevron-left" />
    </button>
    <p :id="titleId" class="date-calendar__title" aria-live="polite">{{ title }}</p>
    <button
      type="button"
      class="date-calendar__nav date-calendar__nav--next"
      :aria-label="t('components.datePicker.nextMonth')"
      :title="t('components.datePicker.nextMonth')"
      :disabled="!canShowNext"
      @click="emit('next')"
    >
      <AppIcon name="chevron-right" />
    </button>
  </div>
</template>
