<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useLandingText } from '@/composables/landing/useLandingText'
import { useLandingTiers } from '@/composables/landing/useLandingTiers'
import { useLevelDial } from '@/composables/landing/useLevelDial'
import { LOCALE_DIR } from '@/constants/locales'
import { clamp } from '@/utils/landing/easing'
import { FULL_SCORE, tierSample } from '@/utils/landing/tiers'
import LandingSectionHead from '@/components/landing/LandingSectionHead.vue'
import LandingLevelSeal from '@/components/landing/LandingLevelSeal.vue'

const props = defineProps({
  section: { type: Object, required: true },
})

const DEFAULT_SAMPLE = 84
const TICK_ENDS = [0, FULL_SCORE]

const { t, locale } = useI18n()
const { text, metaText, heading } = useLandingText()
const { tiers } = useLandingTiers()
const trackRef = ref(null)
const head = computed(() => heading(props.section))
const sample = () => Number(props.section.meta?.sampleScore ?? DEFAULT_SAMPLE)
const { current, value, tier, dragging, go } = useLevelDial(tiers, sample)
const isRtl = computed(() => LOCALE_DIR[locale.value] === 'rtl')
const ticks = computed(() =>
  [...new Set([...TICK_ENDS, ...tiers.value.map((item) => item.min)])].sort((a, b) => a - b),
)
const valueText = computed(() =>
  t('landing.levels.valueText', { value: value.value, level: tier.value?.name ?? '' }),
)

function valueAt(clientX) {
  const box = trackRef.value.getBoundingClientRect()
  const ratio = isRtl.value ? (box.right - clientX) / box.width : (clientX - box.left) / box.width
  return Math.round(clamp(ratio) * FULL_SCORE)
}

function onPointerDown(event) {
  dragging.value = true
  event.currentTarget.setPointerCapture?.(event.pointerId)
  go(valueAt(event.clientX))
}

function onPointerMove(event) {
  if (dragging.value) go(valueAt(event.clientX))
}

function endDrag() {
  dragging.value = false
}
</script>

<template>
  <section class="lp-sec lp-levels" aria-labelledby="lp-levels-title">
    <div class="lp-wrap">
      <LandingSectionHead
        center
        title-id="lp-levels-title"
        :eyebrow="head.eyebrow"
        :title="head.title"
        :highlight="head.highlight"
        :lede="head.lede"
      />
      <div v-if="tiers.length" v-reveal class="lp-lv lp-reveal">
        <div class="lp-lv__show">
          <LandingLevelSeal :tier="tier" />
          <div class="lp-lv__info">
            <span>{{ metaText(section, 'resultLabel') }}</span>
            <b class="lp-lv__num" dir="ltr"
              ><span>{{ value }}</span
              ><small>%</small></b
            >
            <strong :key="tier?.key" class="lp-lv__name" :style="{ '--tier-ink': tier?.ink }">
              {{ tier?.name }}
            </strong>
          </div>
        </div>

        <div
          class="lp-lv__scale"
          :class="{ 'is-dragging': dragging }"
          @pointerdown="onPointerDown"
          @pointermove="onPointerMove"
          @pointerup="endDrag"
          @pointercancel="endDrag"
        >
          <div ref="trackRef" class="lp-lv__track" :style="{ '--v': current.toFixed(2) }">
            <span
              v-for="zone in tiers"
              :key="zone.key"
              class="lp-lv__zone"
              :class="{ 'is-on': zone.key === tier?.key }"
              :style="{ '--a': zone.min, '--b': zone.max ?? 100, '--c': zone.color }"
            ></span>
            <span class="lp-lv__thumb" aria-hidden="true">
              <svg viewBox="0 0 100 100"><use href="#lp-i-arrow" /></svg>
            </span>
          </div>
          <div class="lp-lv__ticks" aria-hidden="true">
            <span v-for="tick in ticks" :key="tick" :style="{ '--x': tick }">{{ tick }}</span>
          </div>
          <label class="lp-sr-only" for="lp-lv-range">{{ t('landing.levels.rangeLabel') }}</label>
          <input
            id="lp-lv-range"
            class="lp-lv__range"
            type="range"
            min="0"
            :max="FULL_SCORE"
            step="1"
            :value="value"
            :aria-valuetext="valueText"
            aria-describedby="lp-lv-hint"
            @input="go($event.target.value)"
          />
        </div>
        <p id="lp-lv-hint" class="lp-lv__hint">{{ metaText(section, 'hint') }}</p>

        <ul class="lp-lv__tiers">
          <li v-for="item in tiers" :key="item.key">
            <button
              type="button"
              :aria-pressed="item.key === tier?.key ? 'true' : 'false'"
              @click="go(tierSample(item))"
            >
              <b>{{ item.name }}</b>
              <span :dir="item.max !== null && item.min > 0 ? 'ltr' : undefined">{{
                item.range
              }}</span>
            </button>
          </li>
        </ul>

        <div class="lp-lv__notes">
          <p v-if="metaText(section, 'verifyNote')">
            <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#lp-i-check" /></svg>
            {{ metaText(section, 'verifyNote') }}
          </p>
          <p v-if="metaText(section, 'settingsNote')" class="lp-muted">
            {{ metaText(section, 'settingsNote') }}
          </p>
        </div>
      </div>
      <p v-else class="lp-head__lede">{{ text(section.description) }}</p>
    </div>
  </section>
</template>
