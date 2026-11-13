<script setup>
import { useI18n } from 'vue-i18n'
import { PASSWORD_LENGTH } from '@/constants/entityEnums'

defineProps({
  id: { type: String, required: true },
  withHistory: { type: Boolean, default: false },
})

const { t } = useI18n()
</script>

<template>
  <div :id="id" class="password-policy">
    <p class="password-policy__title">{{ t('auth.passwordPolicy.title') }}</p>
    <ul class="password-policy__list">
      <li>{{ t('auth.passwordPolicy.length', PASSWORD_LENGTH) }}</li>
      <li>{{ t('auth.passwordPolicy.breached') }}</li>
      <li v-if="withHistory">{{ t('auth.passwordPolicy.history') }}</li>
      <li>{{ t('auth.passwordPolicy.unique') }}</li>
    </ul>
  </div>
</template>
