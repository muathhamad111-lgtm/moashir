<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import {
  AUTH_PANEL_FOOTNOTE_ICON,
  AUTH_PANEL_SHOWS_FOOTNOTE,
  AUTH_PANEL_VALUES,
} from '@/constants/authPanel'

defineProps({
  headingId: { type: String, required: true },
  showFootnote: { type: Boolean, default: AUTH_PANEL_SHOWS_FOOTNOTE },
})

const { t } = useI18n()
</script>

<template>
  <div class="auth-value-panel">
    <h2 :id="headingId" class="auth-value-panel__heading">{{ t('auth.panel.heading') }}</h2>
    <ul class="auth-value-panel__list" role="list">
      <li v-for="value in AUTH_PANEL_VALUES" :key="value.key" class="auth-value-card">
        <span class="auth-value-card__icon">
          <AppIcon :name="value.icon" size="lg" />
        </span>
        <div class="auth-value-card__text">
          <h3 class="auth-value-card__title">{{ t(value.titleKey) }}</h3>
          <p class="auth-value-card__body">{{ t(value.bodyKey) }}</p>
        </div>
      </li>
    </ul>
    <p v-if="showFootnote" class="auth-value-panel__footnote">
      <AppIcon :name="AUTH_PANEL_FOOTNOTE_ICON" size="sm" />
      <span>{{ t('auth.panel.footnote') }}</span>
    </p>
  </div>
</template>
