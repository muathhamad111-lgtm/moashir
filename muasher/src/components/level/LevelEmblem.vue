<script setup>
import { computed } from 'vue'
import { CERTIFICATE_SEALS } from '@/constants/seals'
import { EMBLEM_SIZES, NEUTRAL_TIER_ICONS, TIER_ORDER } from '@/constants/levels'
import { isMetalTier, tierPalette } from '@/utils/levelTier'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  tier: { type: String, required: true, validator: (value) => TIER_ORDER.includes(value) },
  size: { type: String, default: 'm', validator: (value) => EMBLEM_SIZES.includes(value) },
})

const isMetal = computed(() => isMetalTier(props.tier))
const classes = computed(() => [
  'level-emblem',
  `level-emblem--${props.size}`,
  `level-tone--${tierPalette(props.tier)}`,
  isMetal.value ? 'level-emblem--seal' : 'level-emblem--placeholder',
])
</script>

<template>
  <span :class="classes" :data-tier="tier" aria-hidden="true">
    <img v-if="isMetal" class="level-emblem__seal" :src="CERTIFICATE_SEALS[tier]" alt="" />
    <AppIcon v-else class="level-emblem__icon" :name="NEUTRAL_TIER_ICONS[tier]" />
  </span>
</template>
