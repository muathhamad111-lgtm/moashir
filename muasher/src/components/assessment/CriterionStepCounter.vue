<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
  step: { type: Number, required: true },
  total: { type: Number, required: true },
})

const { t } = useI18n()
</script>

<template>
  <p class="step-counter" data-testid="step-counter">
    {{ t('components.steps.counter', { step, total }) }}
  </p>
</template>
