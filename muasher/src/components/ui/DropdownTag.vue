<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  label: { type: String, required: true },
  disabled: { type: Boolean, default: false },
  lockedHint: { type: String, default: '' },
})

const emit = defineEmits(['remove'])
const { t } = useI18n()
</script>

<template>
  <span class="dropdown-tag" :class="{ 'is-locked': lockedHint }" :title="lockedHint || undefined">
    <AppIcon v-if="lockedHint" name="lock" class="dropdown-tag__lock" />
    <span class="dropdown-tag__label">{{ label }}</span>
    <button
      v-if="!disabled && !lockedHint"
      type="button"
      class="dropdown-tag__remove"
      :aria-label="t('components.dropdown.removeTag', { label })"
      @click.stop="emit('remove')"
    >
      <AppIcon name="x" />
    </button>
  </span>
</template>
