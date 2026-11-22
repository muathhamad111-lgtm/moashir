<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'

defineProps({
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['enable', 'disable'])
const { t } = useI18n()
</script>

<template>
  <div class="notification-group-actions">
    <BaseButton variant="ghost" size="sm" :disabled="disabled" @click="emit('enable')">
      {{ t('settings.notifications.catalogue.bulk.enable') }}
    </BaseButton>
    <BaseButton variant="ghost" size="sm" :disabled="disabled" @click="emit('disable')">
      {{ t('settings.notifications.catalogue.bulk.disable') }}
    </BaseButton>
  </div>
</template>
