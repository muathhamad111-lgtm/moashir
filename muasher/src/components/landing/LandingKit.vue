<script setup>
import { computed, ref } from 'vue'
import { useLandingText } from '@/composables/landing/useLandingText'
import { useKitStory } from '@/composables/landing/useKitStory'
import { landingSpot } from '@/directives/landingPointer'
import LandingSectionHead from '@/components/landing/LandingSectionHead.vue'
import LandingGlyph from '@/components/landing/LandingGlyph.vue'
import LandingGapMap from '@/components/landing/LandingGapMap.vue'
import LandingLink from '@/components/landing/LandingLink.vue'

const props = defineProps({
  section: { type: Object, required: true },
})

const vSpot = landingSpot
const { text, metaText, itemsWithRole, heading } = useLandingText()
const stageRef = ref(null)
const head = computed(() => heading(props.section))
const steps = computed(() => itemsWithRole(props.section, 'step'))
const tools = computed(() => itemsWithRole(props.section, 'tool'))
const { step, select, durationOf } = useKitStory(stageRef, () => steps.value.length - 1)
const stageClasses = computed(() =>
  steps.value.map((_, index) => (index <= step.value ? `is-s${index}` : null)).filter(Boolean),
)
</script>

<template>
  <section class="lp-sec lp-kit" aria-labelledby="lp-kit-title">
    <div class="lp-wrap">
      <LandingSectionHead
        title-id="lp-kit-title"
        :eyebrow="head.eyebrow"
        :title="head.title"
        :highlight="head.highlight"
        :lede="head.lede"
      />

      <div v-if="metaText(section, 'noticeTitle')" v-reveal class="lp-kit__pre lp-reveal lp-draw">
        <LandingGlyph name="shield" />
        <div>
          <b>{{ metaText(section, 'noticeTitle') }}</b>
          <p>{{ metaText(section, 'noticeText') }}</p>
        </div>
      </div>

      <div
        v-if="steps.length"
        ref="stageRef"
        class="lp-kit__stage"
        :class="stageClasses"
        :style="{ '--kstep': `${durationOf(step) / 1000}s` }"
      >
        <ol v-reveal class="lp-kit__steps lp-reveal">
          <li
            v-for="(item, index) in steps"
            :key="item.id"
            :class="{ 'is-on': index === step, 'is-done': index < step }"
          >
            <button
              type="button"
              :aria-pressed="index === step ? 'true' : 'false'"
              @click="select(index)"
            >
              <span class="lp-kit__icon"><LandingGlyph :name="item.meta?.glyph" /></span>
              <span class="lp-kit__step-text">
                <b>{{ text(item.title) }}</b>
                <span>{{ text(item.description) }}</span>
              </span>
              <span class="lp-kit__bar" aria-hidden="true"></span>
            </button>
          </li>
        </ol>
        <LandingGapMap />
      </div>

      <div v-if="tools.length" class="lp-kit__tools">
        <article
          v-for="item in tools"
          :key="item.id"
          v-reveal
          v-spot
          class="lp-tool lp-spot lp-reveal lp-draw"
        >
          <LandingGlyph :name="item.meta?.glyph" />
          <b>{{ text(item.title) }}</b>
          <p>{{ text(item.description) }}</p>
        </article>
      </div>

      <div v-if="metaText(section, 'howText')" v-reveal class="lp-kit__how lp-reveal">
        <p>
          <b>{{ metaText(section, 'howLabel') }}</b>
          {{ metaText(section, 'howText') }}
        </p>
        <LandingLink
          v-if="section.ctaUrl"
          class="lp-btn lp-btn--line lp-btn--sm"
          :to="section.ctaUrl"
        >
          {{ text(section.ctaLabel) }}
        </LandingLink>
      </div>
    </div>
  </section>
</template>
