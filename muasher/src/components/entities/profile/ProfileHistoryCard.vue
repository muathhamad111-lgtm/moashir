<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

const emit = defineEmits(['open'])
const { t } = useI18n()
</script>

<template>
  <section class="profile-history-card" data-testid="profile-history-card">
    <h2 class="profile-history-card__title">
      <AppIcon name="clock" />
      <span>{{ t('portal.profile.history.title') }}</span>
    </h2>
    <button type="button" class="profile-history-card__link" @click="emit('open')">
      {{ t('portal.profile.history.viewAll') }}
      <AppIcon name="chevron-sm-right" />
    </button>
  </section>
</template>
