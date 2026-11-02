<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <p class="environment-note" role="note">
    <i class="fa-solid fa-server" aria-hidden="true"></i>
    <span>
      <strong class="environment-note__title">{{ t('integrations.environmentNote.title') }}</strong>
      {{ t('integrations.environmentNote.body') }}
    </span>
  </p>
</template>
