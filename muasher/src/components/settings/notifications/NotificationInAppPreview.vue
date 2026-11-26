<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  title: { type: String, default: '' },
  body: { type: String, default: '' },
  locale: { type: String, required: true },
})

const { t } = useI18n()
</script>

<template>
  <div
    class="notification-preview notification-preview--in-app"
    :dir="locale === 'ar' ? 'rtl' : 'ltr'"
    :lang="locale"
  >
    <div class="notification-preview__item">
      <span class="notification-preview__icon"><AppIcon name="bell" /></span>
      <div>
        <p class="notification-preview__title">{{ title }}</p>
        <p class="notification-preview__text">{{ body }}</p>
        <span class="notification-preview__time">{{
          t('settings.notifications.texts.preview.now', {}, { locale })
        }}</span>
      </div>
    </div>
  </div>
</template>
