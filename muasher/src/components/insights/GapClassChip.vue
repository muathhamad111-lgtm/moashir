<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { GAP_ICONS, GAP_TONES } from '@/constants/insights'

defineProps({
  gapClass: { type: String, required: true },
})

const { t } = useI18n()
</script>

<template>
  <span class="insight-chip" :class="`insight-chip--${GAP_TONES[gapClass]}`" :data-class="gapClass">
    <AppIcon :name="GAP_ICONS[gapClass]" />
    {{ t(`insights.classes.${gapClass}`) }}
  </span>
</template>
