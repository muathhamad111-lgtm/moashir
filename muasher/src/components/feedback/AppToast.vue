<script setup>
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'

const { t } = useI18n()
const { toasts, dismiss } = useToast()
</script>

<template>
  <div class="toast-region" role="status" aria-live="polite">
    <div v-for="item in toasts" :key="item.id" class="toast" :class="`toast--${item.type}`">
      <p class="toast__message">{{ item.message }}</p>
      <button
        type="button"
        class="toast__close"
        :aria-label="t('actions.close')"
        @click="dismiss(item.id)"
      >
        &times;
      </button>
    </div>
  </div>
</template>
