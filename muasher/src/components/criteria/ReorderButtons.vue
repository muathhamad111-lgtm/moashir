<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'

defineProps({
  index: { type: Number, required: true },
  count: { type: Number, required: true },
  label: { type: String, required: true },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['move'])
const { t } = useI18n()
</script>

<template>
  <div
    class="reorder-buttons"
    role="group"
    :aria-label="t('criteria.actions.reorderAria', { name: label })"
  >
    <BaseButton
      variant="ghost"
      size="sm"
      icon-only
      icon="fa-solid fa-arrow-up"
      :disabled="disabled || index === 0"
      :aria-label="t('criteria.actions.moveUpAria', { name: label })"
      @click="emit('move', -1)"
    />
    <BaseButton
      variant="ghost"
      size="sm"
      icon-only
      icon="fa-solid fa-arrow-down"
      :disabled="disabled || index === count - 1"
      :aria-label="t('criteria.actions.moveDownAria', { name: label })"
      @click="emit('move', 1)"
    />
  </div>
</template>
