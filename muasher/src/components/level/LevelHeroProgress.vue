<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { isMetalTier } from '@/utils/levelTier'
import { isTopLevel, nextLevelOf, remainingTo } from '@/utils/levelProgress'
import { formatNumber } from '@/utils/formatNumber'
import { roundToOneDecimal, scorePercentText } from '@/utils/percentText'
import AppIcon from '@/components/ui/AppIcon.vue'
import LevelThresholdTrack from './LevelThresholdTrack.vue'

const props = defineProps({
  tier: { type: String, required: true },
  percent: { type: [Number, String], default: null },
  displayScore: { type: [Number, String], default: null },
  ranges: { type: Array, default: () => [] },
  nextLevelTo: { type: Object, default: null },
})

const { t, n } = useI18n()

const hasPercent = computed(() => props.percent !== null && props.percent !== undefined)
const nextLevel = computed(() => nextLevelOf(props.ranges, props.percent))
const remaining = computed(() => remainingTo(nextLevel.value, props.percent))
const reachedTop = computed(
  () => isMetalTier(props.tier) && isTopLevel(props.ranges, props.percent),
)
const firstThreshold = computed(() => props.ranges[0]?.min ?? null)
const figure = computed(() => formatNumber(n, roundToOneDecimal(props.displayScore ?? 0)))
const scoreValue = computed(() => scorePercentText(n, t, props.percent))
</script>

<template>
  <div class="level-hero__progress">
    <div class="level-hero__score" :class="{ 'level-hero__score--empty': !hasPercent }">
      <p v-if="hasPercent" class="level-hero__figure" data-testid="level-score">
        <span class="level-hero__sr">{{ t('level.hero.scoreValue', { value: scoreValue }) }}</span>
        <bdi class="level-hero__figure-value" dir="ltr" aria-hidden="true">
          <span class="level-hero__number">{{ figure }}</span>
          <span class="level-hero__unit">{{ t('level.hero.unit') }}</span>
        </bdi>
      </p>
      <p v-if="hasPercent" class="level-hero__caption" aria-hidden="true">
        {{ t('level.hero.scoreLabel') }}
      </p>
      <p v-if="reachedTop" class="level-hero__remaining">{{ t('level.track.top') }}</p>
      <i18n-t
        v-else-if="nextLevel"
        keypath="level.track.remaining"
        scope="global"
        tag="p"
        class="level-hero__remaining"
      >
        <template #value>
          <bdi dir="ltr">{{ scorePercentText(n, t, remaining) }}</bdi>
        </template>
        <template #level>{{ t(`level.names.${nextLevel.level}`) }}</template>
      </i18n-t>
      <i18n-t
        v-else-if="!hasPercent && firstThreshold !== null"
        keypath="level.track.start"
        scope="global"
        tag="p"
        class="level-hero__remaining"
      >
        <template #value>
          <bdi dir="ltr">{{ scorePercentText(n, t, firstThreshold) }}</bdi>
        </template>
      </i18n-t>
    </div>
    <p v-if="ranges.length" class="level-hero__journey-title">{{ t('level.track.title') }}</p>
    <router-link
      v-if="ranges.length && nextLevelTo"
      class="level-hero__link"
      :to="nextLevelTo"
      data-testid="hero-next-level"
    >
      {{ t('level.hero.nextLevelLink') }}
      <AppIcon name="arrow-right" />
    </router-link>
    <LevelThresholdTrack
      v-if="ranges.length"
      class="level-hero__track"
      :ranges="ranges"
      :percent="percent"
      :pin-value="displayScore"
    />
  </div>
</template>
