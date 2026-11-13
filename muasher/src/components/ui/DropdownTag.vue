<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  label: { type: String, required: true },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['remove'])
const { t } = useI18n()
</script>

<template>
  <span class="dropdown-tag">
    <span class="dropdown-tag__label">{{ label }}</span>
    <button
      v-if="!disabled"
      type="button"
      class="dropdown-tag__remove"
      :aria-label="t('components.dropdown.removeTag', { label })"
      @click.stop="emit('remove')"
    >
      <AppIcon name="x" />
    </button>
  </span>
</template>
