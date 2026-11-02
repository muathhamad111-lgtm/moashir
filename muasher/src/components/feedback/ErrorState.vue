<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'

defineProps({
  message: { type: String, default: '' },
})

const emit = defineEmits(['retry'])
const { t } = useI18n()
</script>

<template>
  <div class="error-state" role="alert">
    <div class="error-state__art">
      <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
    </div>
    <p class="error-state__message">{{ message || t('common.error') }}</p>
    <div class="error-state__retry">
      <BaseButton variant="secondary" size="sm" icon="fa-solid fa-rotate-right" @click="emit('retry')">
        {{ t('common.retry') }}
      </BaseButton>
    </div>
  </div>
</template>
