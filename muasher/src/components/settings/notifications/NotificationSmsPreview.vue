<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
  text: { type: String, default: '' },
  locale: { type: String, required: true },
})

const { t } = useI18n()
</script>

<template>
  <div
    class="notification-preview notification-preview--sms"
    :dir="locale === 'ar' ? 'rtl' : 'ltr'"
    :lang="locale"
  >
    <div class="notification-preview__phone">
      <span class="notification-preview__sender">{{
        t('settings.notifications.texts.preview.sender', {}, { locale })
      }}</span>
      <p class="notification-preview__bubble">{{ text }}</p>
    </div>
  </div>
</template>
