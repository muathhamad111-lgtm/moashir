<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

const { t } = useI18n()
</script>

<template>
  <p class="environment-note" role="note">
    <AppIcon name="server" />
    <span>
      <strong class="environment-note__title">{{ t('integrations.environmentNote.title') }}</strong>
      {{ t('integrations.environmentNote.body') }}
    </span>
  </p>
</template>
