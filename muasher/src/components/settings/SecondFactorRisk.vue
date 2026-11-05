<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

const RISKS = ['passwordOnly', 'stolenPassword', 'compliance']

const { t } = useI18n()
</script>

<template>
  <div class="second-factor-risk" role="alert">
    <AppIcon name="warning-triangle" class="second-factor-risk__icon" />
    <div class="second-factor-risk__body">
      <p class="second-factor-risk__title">{{ t('settings.signIn.risk.title') }}</p>
      <ul class="second-factor-risk__list">
        <li v-for="risk in RISKS" :key="risk">{{ t(`settings.signIn.risk.items.${risk}`) }}</li>
      </ul>
    </div>
  </div>
</template>
