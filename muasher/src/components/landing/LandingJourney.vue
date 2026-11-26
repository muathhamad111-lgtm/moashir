<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useLandingText } from '@/composables/landing/useLandingText'
import { useJourneyLine } from '@/composables/landing/useJourneyLine'
import { formatPadded } from '@/utils/formatNumber'
import LandingSectionHead from '@/components/landing/LandingSectionHead.vue'
import LandingJourneyScreen from '@/components/landing/LandingJourneyScreen.vue'

const props = defineProps({
  section: { type: Object, required: true },
})

const TAG_LABELS = { all: 'tagAll', accredited: 'tagAccredited', award: 'tagAward' }
const DEFAULT_SCREENS = ['register', 'choose_path', 'evidence', 'review', 'result', 'improvement']

const { n } = useI18n()
const { text, metaText, heading } = useLandingText()
const frameRef = ref(null)
const head = computed(() => heading(props.section))
const steps = computed(() =>
  (props.section.items ?? []).map((item, index) => ({
    item,
    number: formatPadded(n, index + 1),
    screen: item.meta?.screen ?? DEFAULT_SCREENS[index % DEFAULT_SCREENS.length],
    tags: (item.meta?.tags ?? ['all']).filter((tag) => TAG_LABELS[tag]),
  })),
)
const { layout, reached, dashOffset } = useJourneyLine(frameRef, '.lp-jr', '.lp-jr__text')

function tagLabel(tag) {
  return metaText(props.section, TAG_LABELS[tag])
}
</script>

<template>
  <section class="lp-sec lp-journey" aria-labelledby="lp-journey-title">
    <div class="lp-wrap">
      <LandingSectionHead
        title-id="lp-journey-title"
        :eyebrow="head.eyebrow"
        :title="head.title"
        :highlight="head.highlight"
        :lede="head.lede"
      />
      <div ref="frameRef" class="lp-jt">
        <svg
          class="lp-jt__svg"
          :width="layout.width"
          :height="layout.height"
          :viewBox="`0 0 ${layout.width || 1} ${layout.height || 1}`"
          aria-hidden="true"
        >
          <defs>
            <linearGradient
              id="lp-jt-gradient"
              gradientUnits="userSpaceOnUse"
              x1="0"
              y1="0"
              x2="0"
              :y2="layout.height || 1"
            >
              <stop offset="0" class="lp-jt__stop-a" />
              <stop offset=".55" class="lp-jt__stop-b" />
              <stop offset="1" class="lp-jt__stop-c" />
            </linearGradient>
          </defs>
          <path class="lp-jt__bg" :d="layout.d" />
          <path
            class="lp-jt__line"
            :d="layout.d"
            stroke="url(#lp-jt-gradient)"
            :stroke-dasharray="layout.length"
            :stroke-dashoffset="dashOffset"
          />
        </svg>
        <span
          v-for="(dot, index) in layout.dots"
          :key="index"
          class="lp-jt__dot"
          :class="{ 'is-on': reached[index] }"
          :style="{ '--x': `${dot.x}px`, '--y': `${dot.y}px` }"
          aria-hidden="true"
          >{{ steps[index]?.number }}</span
        >
        <ol class="lp-jt__list">
          <li
            v-for="(step, index) in steps"
            :key="step.item.id"
            class="lp-jr"
            :class="{ 'is-on': reached[index] }"
          >
            <div class="lp-jr__text">
              <span class="lp-jr__step">
                {{ metaText(section, 'stepLabel') }} <b dir="ltr">{{ step.number }}</b>
              </span>
              <h3>{{ text(step.item.title) }}</h3>
              <p>{{ text(step.item.description) }}</p>
              <div class="lp-tags">
                <span v-for="tag in step.tags" :key="tag" class="lp-tag" :class="`lp-tag--${tag}`">
                  {{ tagLabel(tag) }}
                </span>
              </div>
            </div>
            <div class="lp-jr__mock">
              <LandingJourneyScreen :screen="step.screen" />
            </div>
          </li>
        </ol>
      </div>
    </div>
  </section>
</template>
