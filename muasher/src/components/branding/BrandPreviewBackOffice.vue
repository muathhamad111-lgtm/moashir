<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const NAV_ITEMS = ['dashboard', 'requests', 'entities', 'settings']
const ACTIVE_ITEM = 'requests'
const BARS = [40, 65, 52, 88, 70]
</script>

<template>
  <div class="brand-sample">
    <div class="brand-sample__bar">
      <span class="brand-sample__search" />
      <span class="brand-sample__avatar" />
    </div>
    <div class="brand-sample__body">
      <ul class="brand-sample__nav">
        <li
          v-for="item in NAV_ITEMS"
          :key="item"
          :class="{ 'brand-sample__nav-item--active': item === ACTIVE_ITEM }"
          class="brand-sample__nav-item"
        >
          {{ t(`branding.preview.sample.nav.${item}`) }}
        </li>
      </ul>
      <div class="brand-sample__main">
        <div class="brand-sample__card">
          <p class="brand-sample__title">{{ t('branding.preview.sample.requests') }}</p>
          <div class="brand-sample__row">
            <span class="brand-sample__button">{{ t('branding.preview.sample.newRequest') }}</span>
            <span class="brand-sample__button brand-sample__button--hover">
              {{ t('branding.preview.sample.hover') }}
            </span>
            <span class="brand-sample__button brand-sample__button--ghost">
              {{ t('branding.preview.sample.export') }}
            </span>
          </div>
          <span class="brand-sample__field">{{ t('branding.preview.sample.search') }}</span>
          <div class="brand-sample__line brand-sample__line--selected">
            <span>{{ t('branding.preview.sample.firstEntity') }}</span>
            <span class="brand-sample__chip">{{ t('branding.preview.sample.inReview') }}</span>
          </div>
          <div class="brand-sample__line">
            <span>{{ t('branding.preview.sample.secondEntity') }}</span>
            <span class="brand-sample__link">{{ t('branding.preview.sample.viewAll') }}</span>
          </div>
        </div>
        <div class="brand-sample__card">
          <p class="brand-sample__caption">{{ t('branding.preview.sample.chart') }}</p>
          <div class="brand-sample__bars">
            <span
              v-for="(height, index) in BARS"
              :key="index"
              :data-height="height"
              class="brand-sample__bar-mark"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
