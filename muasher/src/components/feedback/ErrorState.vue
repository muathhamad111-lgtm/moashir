<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  message: { type: String, default: '' },
})

const emit = defineEmits(['retry'])
const { t } = useI18n()
</script>

<template>
  <div class="error-state" role="alert">
    <div class="error-state__art">
      <AppIcon name="warning-triangle" />
    </div>
    <p class="error-state__message">{{ message || t('common.error') }}</p>
    <div class="error-state__retry">
      <BaseButton variant="secondary" size="sm" icon="refresh" @click="emit('retry')">
        {{ t('common.retry') }}
      </BaseButton>
    </div>
  </div>
</template>
