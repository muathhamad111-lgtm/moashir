<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { LEVEL_TIERS, TIER_ORDER } from '@/constants/levels'
import { isExpiredCertificate, shownValidityOf, tierPalette } from '@/utils/levelTier'
import LevelEmblem from './LevelEmblem.vue'
import LevelValidityChip from './LevelValidityChip.vue'

const props = defineProps({
  tier: { type: String, required: true, validator: (value) => TIER_ORDER.includes(value) },
  size: { type: String, default: 'm', validator: (value) => ['m', 's'].includes(value) },
  reassessment: { type: Boolean, default: false },
  certificate: { type: Object, default: null },
})

const { t } = useI18n()
const validity = computed(() => shownValidityOf(props.certificate))
const isExpired = computed(() => isExpiredCertificate(props.certificate))
const classes = computed(() => [
  'level-badge',
  `level-badge--${props.size}`,
  `level-tone--${isExpired.value ? tierPalette(LEVEL_TIERS.NONE) : tierPalette(props.tier)}`,
  { 'level-badge--expired': isExpired.value },
])
</script>

<template>
  <span class="level-badge__group">
    <span :class="classes" :data-tier="tier" :data-validity="certificate?.validity">
      <LevelEmblem :tier="tier" size="s" />
      <span class="level-badge__label">{{ t(`level.short.${tier}`) }}</span>
    </span>
    <LevelValidityChip
      v-if="validity"
      :validity="validity"
      :valid-until="certificate.validUntil"
      :days-left="certificate.daysUntilExpiry"
    />
    <span v-if="reassessment" class="level-badge__chip">{{ t('level.reassessment') }}</span>
  </span>
</template>
