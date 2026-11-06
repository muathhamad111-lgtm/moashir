<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { TIER_ORDER } from '@/constants/levels'
import { tierPalette } from '@/utils/levelTier'
import { scorePercentText } from '@/utils/percentText'
import { riyadhDateKey } from '@/utils/datetime'
import LevelEmblem from './LevelEmblem.vue'

const props = defineProps({
  tier: { type: String, required: true, validator: (value) => TIER_ORDER.includes(value) },
  certificate: { type: Object, default: null },
  reassessment: { type: Boolean, default: false },
})

const { t, n } = useI18n()
const issuedOn = computed(() =>
  props.certificate?.issuedAt ? riyadhDateKey(props.certificate.issuedAt) : '',
)
</script>

<template>
  <section
    class="level-ribbon"
    :class="`level-tone--${tierPalette(tier)}`"
    :data-tier="tier"
    :aria-label="t('level.staff.entityLevel')"
    data-testid="level-ribbon"
  >
    <LevelEmblem :tier="tier" size="m" />
    <div class="level-ribbon__copy">
      <p class="level-ribbon__eyebrow">{{ t('level.staff.entityLevel') }}</p>
      <p class="level-ribbon__name">
        {{ t(`level.names.${tier}`) }}
        <span v-if="reassessment" class="level-ribbon__chip" data-testid="level-reassessment">
          {{ t('level.reassessment') }}
        </span>
      </p>
      <p v-if="certificate" class="level-ribbon__meta">
        <bdi dir="ltr">{{ certificate.number }}</bdi>
        <span aria-hidden="true">·</span>
        <bdi dir="ltr">{{ issuedOn }}</bdi>
      </p>
    </div>
    <bdi v-if="certificate?.percent" class="level-ribbon__percent" dir="ltr">
      {{ scorePercentText(n, t, certificate.percent) }}
    </bdi>
  </section>
</template>
