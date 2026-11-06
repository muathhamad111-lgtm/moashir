<script setup>
import { HOME_PILL_TONES } from '@/constants/homePills'
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  tone: {
    type: String,
    default: 'soon',
    validator: (value) => HOME_PILL_TONES.includes(value),
  },
  icon: { type: String, default: '' },
})
</script>

<template>
  <span class="home-pill" :class="`home-pill--${tone}`">
    <AppIcon v-if="icon" :name="icon" class="home-pill__icon" />
    <span v-else class="home-pill__dot" aria-hidden="true"></span>
    <span class="home-pill__label"><slot /></span>
  </span>
</template>
