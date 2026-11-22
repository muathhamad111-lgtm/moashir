<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'

defineProps({
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['format'])
const { t } = useI18n()

const FORMATS = ['bold', 'link', 'list']
</script>

<template>
  <div
    class="notification-format-bar"
    role="toolbar"
    :aria-label="t('settings.notifications.texts.format.label')"
  >
    <BaseButton
      v-for="format in FORMATS"
      :key="format"
      variant="ghost"
      size="sm"
      :disabled="disabled"
      @click="emit('format', format)"
    >
      {{ t(`settings.notifications.texts.format.${format}`) }}
    </BaseButton>
  </div>
</template>
