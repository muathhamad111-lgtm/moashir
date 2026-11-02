<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
  compact: { type: Boolean, default: false },
})

const { t } = useI18n()
</script>

<template>
  <p class="test-mode-notice" :class="{ 'test-mode-notice--compact': compact }" role="note">
    <i class="fa-solid fa-flask" aria-hidden="true"></i>
    <span>
      <strong class="test-mode-notice__title">{{ t('finance.testMode.title') }}</strong>
      <span v-if="!compact" class="test-mode-notice__body">{{ t('finance.testMode.body') }}</span>
    </span>
  </p>
</template>
