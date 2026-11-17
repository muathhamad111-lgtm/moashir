<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'

defineProps({
  isFirst: { type: Boolean, default: true },
  isLast: { type: Boolean, default: true },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['previous', 'next'])
const { t } = useI18n()
</script>

<template>
  <div class="step-buttons">
    <BaseButton
      v-if="!isFirst"
      variant="ghost"
      icon="arrow-left"
      data-testid="step-previous"
      :disabled="disabled"
      @click="emit('previous')"
    >
      {{ t('components.steps.previous') }}
    </BaseButton>
    <slot />
    <BaseButton
      v-if="!isLast"
      variant="primary"
      icon-end="arrow-right"
      data-testid="step-next"
      :disabled="disabled"
      @click="emit('next')"
    >
      {{ t('components.steps.next') }}
    </BaseButton>
    <slot v-else name="last" />
  </div>
</template>
