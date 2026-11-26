<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const NAV_ITEMS = ['journey', 'assessments', 'certificates', 'payments']
const ACTIVE_ITEM = 'journey'
</script>

<template>
  <div class="brand-sample brand-sample--portal">
    <div class="brand-sample__portal-bar">
      <span class="brand-sample__brand">{{ t('branding.preview.sample.platform') }}</span>
      <ul class="brand-sample__tabs">
        <li
          v-for="item in NAV_ITEMS"
          :key="item"
          :class="{ 'brand-sample__tab--active': item === ACTIVE_ITEM }"
          class="brand-sample__tab"
        >
          {{ t(`branding.preview.sample.portalNav.${item}`) }}
        </li>
      </ul>
    </div>
    <div class="brand-sample__hero">
      <p class="brand-sample__hero-title">{{ t('branding.preview.sample.heroTitle') }}</p>
      <p class="brand-sample__hero-text">{{ t('branding.preview.sample.heroText') }}</p>
      <span class="brand-sample__progress"><span class="brand-sample__progress-fill" /></span>
      <span class="brand-sample__button">{{ t('branding.preview.sample.start') }}</span>
    </div>
    <div class="brand-sample__tiles">
      <div class="brand-sample__card">
        <p class="brand-sample__title">{{ t('branding.preview.sample.certificates') }}</p>
        <span class="brand-sample__link">{{ t('branding.preview.sample.viewAll') }}</span>
      </div>
      <div class="brand-sample__card">
        <p class="brand-sample__title">{{ t('branding.preview.sample.messages') }}</p>
        <span class="brand-sample__chip">{{ t('branding.preview.sample.unread') }}</span>
      </div>
    </div>
  </div>
</template>
