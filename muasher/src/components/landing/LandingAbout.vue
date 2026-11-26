<script setup>
import { computed, ref } from 'vue'
import { useLandingText } from '@/composables/landing/useLandingText'
import { useManifesto } from '@/composables/landing/useManifesto'
import { manifestoWords } from '@/utils/landing/manifesto'
import { landingSpot, landingTilt } from '@/directives/landingPointer'
import { formatPadded } from '@/utils/formatNumber'
import { useI18n } from 'vue-i18n'
import LandingSectionHead from '@/components/landing/LandingSectionHead.vue'
import LandingGlyph from '@/components/landing/LandingGlyph.vue'

const props = defineProps({
  section: { type: Object, required: true },
})

const vTilt = landingTilt
const vSpot = landingSpot

const { n } = useI18n()
const { text, metaText, itemsWithRole, heading } = useLandingText()
const manifestoRef = ref(null)
const head = computed(() => heading(props.section))
const words = computed(() =>
  manifestoWords(text(props.section.description), metaText(props.section, 'keyWords')),
)
const audience = computed(() => itemsWithRole(props.section, 'audience'))
const goals = computed(() => itemsWithRole(props.section, 'goal'))
const { lit } = useManifesto(manifestoRef, () => words.value.length)
</script>

<template>
  <section class="lp-sec lp-about" aria-labelledby="lp-about-title">
    <div class="lp-wrap">
      <LandingSectionHead
        title-id="lp-about-title"
        :eyebrow="head.eyebrow"
        :title="head.title"
        :highlight="head.highlight"
      />
      <p ref="manifestoRef" class="lp-manifesto">
        <template v-for="(entry, index) in words" :key="entry.key">
          <span
            class="lp-manifesto__word"
            :class="{ 'is-key': entry.isKey, 'is-lit': index < lit }"
            >{{ entry.word }}</span
          >{{ ' ' }}
        </template>
      </p>

      <div v-if="audience.length" class="lp-serves">
        <h3 v-reveal class="lp-serves__label lp-reveal">
          {{ metaText(section, 'audienceLabel') }}
        </h3>
        <ul class="lp-serves__row">
          <li
            v-for="item in audience"
            :key="item.id"
            v-reveal
            v-tilt
            class="lp-who lp-reveal lp-draw"
          >
            <LandingGlyph :name="item.meta?.glyph" />
            <b>{{ text(item.title) }}</b>
          </li>
        </ul>
      </div>

      <div v-if="goals.length" class="lp-goals">
        <h3 v-reveal class="lp-goals__title lp-reveal">{{ metaText(section, 'goalsTitle') }}</h3>
        <ol class="lp-goals__grid">
          <li
            v-for="(item, index) in goals"
            :key="item.id"
            v-reveal
            v-spot
            class="lp-goal lp-spot lp-reveal lp-draw"
          >
            <span class="lp-goal__top">
              <LandingGlyph :name="item.meta?.glyph" />
              <span class="lp-goal__num" aria-hidden="true">{{ formatPadded(n, index + 1) }}</span>
            </span>
            <p>{{ text(item.description) || text(item.title) }}</p>
          </li>
        </ol>
      </div>
    </div>
  </section>
</template>
