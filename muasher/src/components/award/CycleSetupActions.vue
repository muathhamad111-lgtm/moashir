<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'

defineProps({
  showsPrevious: { type: Boolean, default: true },
  nextLabel: { type: String, default: '' },
  nextType: { type: String, default: 'button' },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['previous', 'next'])
const { t } = useI18n()
</script>

<template>
  <div class="cycle-setup__actions">
    <BaseButton
      v-if="showsPrevious"
      variant="ghost"
      icon="arrow-left"
      class="cycle-setup__previous"
      @click="emit('previous')"
    >
      {{ t('cycles.setup.previous') }}
    </BaseButton>
    <slot />
    <BaseButton
      v-if="nextLabel"
      :type="nextType"
      variant="primary"
      icon-end="arrow-right"
      class="cycle-setup__next"
      :loading="busy"
      @click="nextType === 'button' && emit('next')"
    >
      {{ nextLabel }}
    </BaseButton>
  </div>
</template>
