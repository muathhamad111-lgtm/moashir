<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCertificateLevels } from '@/composables/useCertificateLevels'
import { levelOfScore } from '@/utils/levelRanges'
import { percentText } from '@/utils/percentText'
import HomePill from '@/components/public/HomePill.vue'
import ScoreRing from '@/components/public/ScoreRing.vue'
import MeterBar from '@/components/public/MeterBar.vue'
import LevelRange from '@/components/public/LevelRange.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const BADGE_TONES = ['gold', 'accent']

const props = defineProps({
  preview: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const { n, t } = useI18n()
const { ranges } = useCertificateLevels()

const bars = computed(() => (Array.isArray(props.preview.bars) ? props.preview.bars : []))
const badges = computed(() => (Array.isArray(props.preview.badges) ? props.preview.badges : []))
const score = computed(() => Number(props.preview.score) || 0)
const level = computed(() => levelOfScore(ranges.value, score.value))

function toneOf(index) {
  return BADGE_TONES[index % BADGE_TONES.length]
}
</script>

<template>
  <figure v-reveal class="hero-preview" :aria-label="t('public.home.preview.label')">
    <div class="hero-preview__card">
      <div class="hero-preview__head">
        <div class="hero-preview__heading">
          <strong class="hero-preview__title">{{ localized(preview.title) }}</strong>
          <span v-if="preview.caption" class="hero-preview__caption">{{
            localized(preview.caption)
          }}</span>
        </div>
        <HomePill tone="preview">{{ t('public.home.preview.label') }}</HomePill>
      </div>
      <div class="hero-preview__score">
        <ScoreRing :value="score" :label="t('public.home.preview.scoreLabel')" />
        <div v-if="level" class="hero-preview__level">
          <HomePill :tone="level.level">{{ localized(level.label) }}</HomePill>
          <LevelRange class="hero-preview__range" :range="level" />
          <span class="hero-preview__note">{{ t('public.home.preview.levelNote') }}</span>
        </div>
      </div>
      <ul v-if="bars.length" class="hero-preview__bars">
        <li v-for="(bar, index) in bars" :key="index" class="hero-preview__bar">
          <span class="hero-preview__bar-row">
            <span class="hero-preview__bar-label">{{ localized(bar.label) }}</span>
            <bdi class="hero-preview__bar-value" dir="ltr">{{
              percentText(n, t, Number(bar.value) || 0)
            }}</bdi>
          </span>
          <MeterBar :value="Number(bar.value) || 0" />
        </li>
      </ul>
    </div>
    <ul v-if="badges.length" class="hero-preview__badges">
      <li
        v-for="(badge, index) in badges"
        :key="index"
        class="hero-preview__badge"
        :class="`hero-preview__badge--${index % 2 ? 'start' : 'end'}`"
      >
        <span
          class="hero-preview__badge-icon"
          :class="`hero-preview__badge-icon--${toneOf(index)}`"
          aria-hidden="true"
          ><AppIcon :name="badge.icon"
        /></span>
        <span class="hero-preview__badge-text">
          <strong>{{ localized(badge.title) }}</strong>
          <span v-if="badge.caption">{{ localized(badge.caption) }}</span>
        </span>
      </li>
    </ul>
  </figure>
</template>
