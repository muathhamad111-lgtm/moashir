<script setup>
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { TIER_ORDER } from '@/constants/levels'
import { isMetalTier, tierPalette } from '@/utils/levelTier'
import { isTopLevel, nextLevelOf, remainingTo } from '@/utils/levelProgress'
import { scorePercentText } from '@/utils/percentText'
import { riyadhDateKey } from '@/utils/datetime'
import LevelEmblem from './LevelEmblem.vue'
import LevelScoreRing from './LevelScoreRing.vue'
import LevelThresholdTrack from './LevelThresholdTrack.vue'

const props = defineProps({
  tier: { type: String, required: true, validator: (value) => TIER_ORDER.includes(value) },
  certificate: { type: Object, default: null },
  ranges: { type: Array, default: () => [] },
  reassessment: { type: Boolean, default: false },
  validUntil: { type: String, default: null },
  audience: { type: String, default: 'entity', validator: (v) => ['entity', 'staff'].includes(v) },
})

const { t, n } = useI18n()
const nameId = useId()

const percent = computed(() => props.certificate?.percent ?? null)
const hasPercent = computed(() => percent.value !== null)
const nextLevel = computed(() => nextLevelOf(props.ranges, percent.value))
const remaining = computed(() => remainingTo(nextLevel.value, percent.value))
const reachedTop = computed(
  () => isMetalTier(props.tier) && isTopLevel(props.ranges, percent.value),
)
const issuedOn = computed(() =>
  props.certificate?.issuedAt ? riyadhDateKey(props.certificate.issuedAt) : '',
)
const firstThreshold = computed(() => props.ranges[0]?.min ?? null)
const classes = computed(() => [
  `level-tone--${tierPalette(props.tier)}`,
  `level-hero--${props.tier}`,
])
</script>

<template>
  <section class="level-hero" :class="classes" :data-tier="tier" :aria-labelledby="nameId">
    <div class="level-hero__identity">
      <LevelEmblem class="level-hero__emblem" :tier="tier" size="xl" />
      <div class="level-hero__copy">
        <p class="level-hero__eyebrow">{{ t(`level.hero.eyebrow.${audience}`) }}</p>
        <h2 :id="nameId" class="level-hero__name">{{ t(`level.names.${tier}`) }}</h2>
        <p class="level-hero__lead">{{ t(`level.hero.lead.${tier}`) }}</p>
        <dl v-if="certificate" class="level-hero__facts">
          <div class="level-hero__fact">
            <dt>{{ t('level.hero.number') }}</dt>
            <dd>
              <bdi dir="ltr">{{ certificate.number }}</bdi>
            </dd>
          </div>
          <div class="level-hero__fact">
            <dt>{{ t('level.hero.issuedAt') }}</dt>
            <dd>
              <bdi dir="ltr">{{ issuedOn }}</bdi>
            </dd>
          </div>
          <div v-if="validUntil" class="level-hero__fact" data-testid="level-valid-until">
            <dt>{{ t('level.hero.validUntil') }}</dt>
            <dd>
              <bdi dir="ltr">{{ validUntil }}</bdi>
            </dd>
          </div>
        </dl>
        <p class="level-hero__chips">
          <span v-if="certificate" class="level-hero__chip level-hero__chip--valid">
            {{ t('level.hero.valid') }}
          </span>
          <span
            v-if="reassessment"
            class="level-hero__chip level-hero__chip--reassessment"
            data-testid="level-reassessment"
          >
            {{ t('level.reassessment') }}
          </span>
        </p>
        <div v-if="$slots.actions" class="level-hero__actions">
          <slot name="actions" />
        </div>
      </div>
      <LevelScoreRing
        v-if="hasPercent"
        class="level-hero__ring"
        :value="percent"
        :label="t('level.hero.ringLabel')"
      />
    </div>

    <div v-if="ranges.length" class="level-hero__ladder">
      <div class="level-hero__ladder-head">
        <p class="level-hero__ladder-title">{{ t(`level.track.title.${audience}`) }}</p>
        <p v-if="reachedTop" class="level-hero__next">{{ t(`level.track.top.${audience}`) }}</p>
        <i18n-t
          v-else-if="nextLevel"
          keypath="level.track.remaining"
          scope="global"
          tag="p"
          class="level-hero__next"
        >
          <template #value>
            <bdi dir="ltr">{{ scorePercentText(n, t, remaining) }}</bdi>
          </template>
          <template #level>{{ t(`level.names.${nextLevel.level}`) }}</template>
        </i18n-t>
        <i18n-t
          v-else-if="!hasPercent"
          keypath="level.track.start"
          scope="global"
          tag="p"
          class="level-hero__next"
        >
          <template #value>
            <bdi dir="ltr">{{ scorePercentText(n, t, firstThreshold) }}</bdi>
          </template>
        </i18n-t>
      </div>
      <LevelThresholdTrack
        :ranges="ranges"
        :percent="percent"
        :pin-label-key="`level.track.pin.${audience}`"
      />
    </div>
  </section>
</template>
