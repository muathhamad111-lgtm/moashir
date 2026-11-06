<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import NotificationPreferencesDialog from './NotificationPreferencesDialog.vue'

const { t } = useI18n()
const open = ref(false)
</script>

<template>
  <section class="notification-preferences-card" aria-labelledby="notification-preferences-title">
    <h2 id="notification-preferences-title" class="notification-preferences-card__title">
      {{ t('notifications.preferences.title') }}
    </h2>
    <p class="notification-preferences-card__text">{{ t('notifications.preferences.summary') }}</p>
    <BaseButton variant="secondary" icon="gear" @click="open = true">
      {{ t('notifications.preferences.manage') }}
    </BaseButton>
    <NotificationPreferencesDialog v-model="open" />
  </section>
</template>
