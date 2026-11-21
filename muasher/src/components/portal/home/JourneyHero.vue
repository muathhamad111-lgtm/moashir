<script setup>
import { useI18n } from 'vue-i18n'
import { usePortalIdentity } from '@/composables/usePortalIdentity'

const { t } = useI18n()
const { firstName, entityName } = usePortalIdentity()
</script>

<template>
  <header class="journey-hero">
    <p class="journey-hero__greeting">{{ t('portal.home.hero.title', { name: firstName }) }}</p>
    <h1 class="journey-hero__title">{{ entityName }}</h1>
  </header>
</template>
