<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useTheme } from '@/composables/useTheme'
import { THEMES } from '@/constants/themes'

const { t } = useI18n()
const { theme, toggleTheme } = useTheme()

const icon = computed(() =>
  theme.value === THEMES.dark ? 'fa-solid fa-sun' : 'fa-solid fa-moon',
)
const label = computed(() =>
  theme.value === THEMES.dark ? t('theme.switchToLight') : t('theme.switchToDark'),
)
</script>

<template>
  <BaseButton variant="ghost" size="sm" icon-only :icon="icon" :aria-label="label" @click="toggleTheme" />
</template>
