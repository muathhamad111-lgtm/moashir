<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  accentToken: { type: String, required: true },
  logo: { type: String, required: true },
})

const { t } = useI18n()
const MODES = ['dark', 'light']
</script>

<template>
  <div class="branding-preview">
    <figure
      v-for="mode in MODES"
      :key="mode"
      class="branding-preview__frame"
      :data-theme="mode"
      :data-accent="accentToken"
    >
      <figcaption class="branding-preview__caption">{{ t(`branding.preview.${mode}`) }}</figcaption>
      <div class="branding-preview__shell" aria-hidden="true">
        <div class="branding-preview__sidebar">
          <img class="branding-preview__logo" :src="logo" alt="" />
          <span class="branding-preview__item">
            <AppIcon name="gauge" />{{ t('branding.preview.menuDashboard') }}
          </span>
          <span class="branding-preview__item is-active">
            <AppIcon name="inbox" />{{ t('branding.preview.menuRequests') }}
          </span>
          <span class="branding-preview__item">
            <AppIcon name="bell" />{{ t('branding.preview.menuNotifications') }}
          </span>
        </div>
        <div class="branding-preview__content">
          <span class="branding-preview__heading">{{ t('branding.preview.heading') }}</span>
          <span class="branding-preview__link">{{ t('branding.preview.link') }}</span>
          <span class="btn btn--primary btn--sm">{{ t('branding.preview.button') }}</span>
        </div>
      </div>
    </figure>
  </div>
</template>
