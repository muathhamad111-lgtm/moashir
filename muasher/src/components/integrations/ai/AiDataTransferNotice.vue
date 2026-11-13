<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

const CONTROLS = ['scrub', 'noStorage', 'noIdentifiers']

const { t } = useI18n()
</script>

<template>
  <section class="data-transfer" :aria-label="t('integrations.ai.transfer.title')">
    <p class="data-transfer__warning" role="note">
      <AppIcon name="globe" />
      <span>
        <strong>{{ t('integrations.ai.transfer.title') }}</strong>
        {{ t('integrations.ai.transfer.body') }}
      </span>
    </p>
    <ul class="data-transfer__controls">
      <li v-for="control in CONTROLS" :key="control">
        {{ t(`integrations.ai.transfer.controls.${control}`) }}
      </li>
    </ul>
  </section>
</template>
