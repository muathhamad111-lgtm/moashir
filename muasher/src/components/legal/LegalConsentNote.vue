<script setup>
import { useI18n } from 'vue-i18n'
import LegalLink from './LegalLink.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  kinds: { type: Array, required: true },
})

const { t } = useI18n()
</script>

<template>
  <p class="legal-consent">
    <AppIcon name="doc" />
    <span>{{ t('legal.readBefore') }}</span>
    <template v-for="(kind, index) in kinds" :key="kind">
      <span v-if="index > 0" aria-hidden="true">·</span>
      <LegalLink :kind="kind" new-tab />
    </template>
  </p>
</template>
