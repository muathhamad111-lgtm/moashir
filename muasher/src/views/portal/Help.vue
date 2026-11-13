<script setup>
import { useI18n } from 'vue-i18n'
import PathGuideCard from '@/components/portal/help/PathGuideCard.vue'
import HomeContactForm from '@/components/public/HomeContactForm.vue'

const GUIDED_PATHS = ['accredited', 'advanced']

const { t } = useI18n()
</script>

<template>
  <section class="portal-help">
    <header class="portal-help__header">
      <p class="portal-help__eyebrow">{{ t('portal.help.eyebrow') }}</p>
      <h1 class="portal-help__title">{{ t('portal.help.title') }}</h1>
      <p class="portal-help__subtitle">{{ t('portal.help.subtitle') }}</p>
    </header>
    <div class="portal-help__layout">
      <div class="portal-help__guides">
        <PathGuideCard v-for="path in GUIDED_PATHS" :key="path" :path="path" />
      </div>
      <section class="portal-help__message" :aria-label="t('portal.help.newMessage')">
        <p class="portal-help__message-text">{{ t('portal.help.newMessageText') }}</p>
        <HomeContactForm />
      </section>
    </div>
  </section>
</template>
