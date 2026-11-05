<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
</script>

<template>
  <p class="test-mode-banner" role="status">
    <AppIcon name="flask" />
    <span>
      <strong class="test-mode-banner__title">{{
        t('integrations.payment.testBanner.title')
      }}</strong>
      {{ t('integrations.payment.testBanner.body') }}
    </span>
  </p>
</template>
