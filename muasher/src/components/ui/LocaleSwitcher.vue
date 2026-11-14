<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useLocale } from '@/composables/useLocale'

const { t } = useI18n()
const { toggleLocale } = useLocale()
</script>

<template>
  <BaseButton
    variant="ghost"
    size="sm"
    icon="language"
    :aria-label="t('locale.switchLabel')"
    @click="toggleLocale"
  >
    <span class="locale-switcher__full">{{ t('locale.switchLabel') }}</span>
    <span class="locale-switcher__short" aria-hidden="true">{{ t('locale.switchShort') }}</span>
  </BaseButton>
</template>
