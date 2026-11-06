<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { TIER_ORDER } from '@/constants/levels'
import { tierPalette } from '@/utils/levelTier'
import LevelEmblem from './LevelEmblem.vue'

const props = defineProps({
  tier: { type: String, required: true, validator: (value) => TIER_ORDER.includes(value) },
  size: { type: String, default: 'm', validator: (value) => ['m', 's'].includes(value) },
  reassessment: { type: Boolean, default: false },
})

const { t } = useI18n()
const classes = computed(() => [
  'level-badge',
  `level-badge--${props.size}`,
  `level-tone--${tierPalette(props.tier)}`,
])
</script>

<template>
  <span class="level-badge__group">
    <span :class="classes" :data-tier="tier">
      <LevelEmblem :tier="tier" size="s" />
      <span class="level-badge__label">{{ t(`level.short.${tier}`) }}</span>
    </span>
    <span v-if="reassessment" class="level-badge__chip">{{ t('level.reassessment') }}</span>
  </span>
</template>
