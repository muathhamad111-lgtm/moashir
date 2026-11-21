<script setup>
import { computed, ref } from 'vue'
import { useLandingText } from '@/composables/landing/useLandingText'
import { useLensCycle } from '@/composables/landing/useLensCycle'
import { lensArcs, lensRotation, lensTicks } from '@/utils/landing/lensRing'
import LandingSectionHead from '@/components/landing/LandingSectionHead.vue'
import LandingLink from '@/components/landing/LandingLink.vue'

const props = defineProps({
  section: { type: Object, required: true },
})

const POINTER = 'translate(200 21) rotate(180) scale(.2) translate(-50 -51)'

const { text, metaText, heading } = useLandingText()
const sectionRef = ref(null)
const head = computed(() => heading(props.section))
const lenses = computed(() => props.section.items ?? [])
const ticks = lensTicks()
const arcs = computed(() => lensArcs(lenses.value.length))
const { active, select } = useLensCycle(sectionRef, () => lenses.value.length)
const rotation = computed(() => ({
  '--lp-lens-turn': `${lensRotation(active.value, lenses.value.length)}deg`,
}))
const activeName = computed(() => text(lenses.value[active.value]?.title))
</script>

<template>
  <section
    ref="sectionRef"
    class="lp-sec lp-deep lp-lenses"
    data-lp-deep
    aria-labelledby="lp-lenses-title"
  >
    <div class="lp-deep-pattern lp-deep-pattern--alt" aria-hidden="true"></div>
    <div class="lp-wrap">
      <LandingSectionHead
        title-id="lp-lenses-title"
        :eyebrow="head.eyebrow"
        :title="head.title"
        :highlight="head.highlight"
        :lede="head.lede"
      />
      <div class="lp-lenses__grid">
        <div v-reveal class="lp-lenses__ring-wrap lp-reveal">
          <svg class="lp-lenses__ring" viewBox="0 0 400 400" aria-hidden="true">
            <line
              v-for="tick in ticks"
              :key="tick.key"
              class="lp-lenses__tick"
              :class="{ 'is-major': tick.major }"
              :x1="tick.x1"
              :y1="tick.y1"
              :x2="tick.x2"
              :y2="tick.y2"
            />
            <g class="lp-lenses__rot" :style="rotation">
              <template v-for="arc in arcs" :key="arc.index">
                <path
                  class="lp-lenses__arc"
                  :class="{ 'is-on': arc.index === active }"
                  :d="arc.d"
                  @click="select(arc.index)"
                />
                <circle class="lp-lenses__dot" :cx="arc.cx" :cy="arc.cy" r="3.5" />
              </template>
            </g>
            <g class="lp-lenses__inner">
              <circle cx="200" cy="200" r="118" class="lp-lenses__inner-a" />
              <circle cx="200" cy="200" r="106" class="lp-lenses__inner-b" />
            </g>
            <path
              class="lp-lenses__pointer"
              :transform="POINTER"
              d="M50 18 94 62 72 84 50 62 28 84 6 62Z"
            />
          </svg>
          <div class="lp-lenses__center" aria-hidden="true">
            <span class="lp-lenses__center-icon">
              <svg viewBox="0 0 24 24"><use href="#lp-i-lens" /></svg>
            </span>
            <span :key="active" class="lp-lenses__center-name">{{ activeName }}</span>
          </div>
        </div>
        <div v-reveal class="lp-lenses__side lp-reveal">
          <ul class="lp-lenses__list">
            <li v-for="(lens, index) in lenses" :key="lens.id">
              <button
                type="button"
                :aria-pressed="index === active ? 'true' : 'false'"
                :aria-controls="`lp-lens-desc-${lens.id}`"
                @click="select(index)"
              >
                {{ text(lens.title) }}
                <span
                  v-show="index === active"
                  :id="`lp-lens-desc-${lens.id}`"
                  class="lp-lenses__desc"
                >
                  {{ text(lens.description) }}
                </span>
              </button>
            </li>
          </ul>
          <p v-if="metaText(section, 'note')" class="lp-lenses__note">
            {{ metaText(section, 'note') }}
          </p>
          <LandingLink
            v-if="section.ctaUrl"
            class="lp-btn lp-btn--fill lp-btn--sm"
            :to="section.ctaUrl"
          >
            {{ text(section.ctaLabel) }}
          </LandingLink>
        </div>
      </div>
    </div>
  </section>
</template>
