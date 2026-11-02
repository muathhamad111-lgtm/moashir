<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useLocale } from '@/composables/useLocale'

const { t } = useI18n()
const { toggleLocale } = useLocale()
</script>

<template>
  <BaseButton variant="ghost" size="sm" icon="fa-solid fa-language" @click="toggleLocale">
    {{ t('locale.switchLabel') }}
  </BaseButton>
</template>
