<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <p class="test-mode-banner" role="status">
    <i class="fa-solid fa-flask" aria-hidden="true"></i>
    <span>
      <strong class="test-mode-banner__title">{{ t('integrations.payment.testBanner.title') }}</strong>
      {{ t('integrations.payment.testBanner.body') }}
    </span>
  </p>
</template>
