<script setup>
import { computed, onMounted, ref } from 'vue'
import { useTheme } from '@/composables/useTheme'
import { useLandingText } from '@/composables/landing/useLandingText'
import { useLandingTiers } from '@/composables/landing/useLandingTiers'
import { useHeroStage } from '@/composables/landing/useHeroStage'
import { useHeroDots } from '@/composables/landing/useHeroDots'
import { useLandingReady } from '@/composables/landing/useLandingReady'
import { THEMES } from '@/constants/themes'
import LandingLink from '@/components/landing/LandingLink.vue'
import trophy from '@/assets/landing/trophy.webp'
import trophySmall from '@/assets/landing/trophy-sm.webp'

const props = defineProps({
  section: { type: Object, required: true },
})

const TROPHY_SIZES = '(max-width: 760px) 300px, 360px'
const TROPHY_WIDTH = 720
const TROPHY_HEIGHT = 1157

const { text, metaText, firstWithRole } = useLandingText()
const { theme } = useTheme()
const { tiers, sealTiers } = useLandingTiers()
const { onReady } = useLandingReady()

const heroRef = ref(null)
const pinRef = ref(null)
const copyRef = ref(null)
const trophyRef = ref(null)
const patternRef = ref(null)
const canvasRef = ref(null)
const shineRef = ref(null)

const secondary = computed(() => firstWithRole(props.section, 'secondary-cta'))
const customTrophy = computed(() => props.section.image?.url ?? '')
const trophySrc = computed(() => customTrophy.value || trophy)
const trophySrcset = computed(() =>
  customTrophy.value ? undefined : `${trophySmall} 420w, ${trophy} 720w`,
)
const maskStyle = computed(() => ({
  '--lp-trophy-mask': `url("${customTrophy.value || trophySmall}")`,
}))
const isDark = computed(() => theme.value === THEMES.dark)

function sweep() {
  const shine = shineRef.value
  if (!shine) return
  shine.classList.remove('is-sweeping')
  void shine.offsetWidth
  shine.classList.add('is-sweeping')
}

const { score, tier, start } = useHeroStage({
  heroRef,
  pinRef,
  copyRef,
  trophyRef,
  tiers,
  onTierChange: sweep,
})

useHeroDots({ canvasRef, pinRef, copyRef, patternRef, isDark })

onMounted(() => onReady(start))
</script>

<template>
  <section ref="heroRef" class="lp-hero" aria-labelledby="lp-hero-title">
    <div ref="pinRef" class="lp-hero__pin">
      <div class="lp-hero__bg" aria-hidden="true">
        <div ref="patternRef" class="lp-hero__pattern"></div>
        <canvas ref="canvasRef" class="lp-hero__dots"></canvas>
        <div class="lp-hero__glow"></div>
      </div>

      <div ref="copyRef" class="lp-hero__copy">
        <p v-if="metaText(section, 'eyebrow')" class="lp-hero__chip lp-intro">
          <svg viewBox="0 0 100 100" aria-hidden="true"><use href="#lp-i-arrow" /></svg>
          {{ metaText(section, 'eyebrow') }}
        </p>
        <h1 id="lp-hero-title" class="lp-hero__title lp-intro">
          {{ text(section.title) }}
          <template v-if="metaText(section, 'highlight')">
            <br />
            <span>{{ metaText(section, 'highlight') }}</span>
          </template>
        </h1>
        <p v-if="text(section.description)" class="lp-hero__sub lp-intro">
          {{ text(section.description) }}
        </p>
        <div class="lp-cta-pair lp-intro">
          <LandingLink v-if="section.ctaUrl" class="lp-btn lp-btn--fill" :to="section.ctaUrl">
            {{ text(section.ctaLabel) }}
          </LandingLink>
          <LandingLink v-if="secondary?.ctaUrl" class="lp-btn lp-btn--line" :to="secondary.ctaUrl">
            {{ text(secondary.ctaLabel) || text(secondary.title) }}
          </LandingLink>
        </div>
      </div>

      <div class="lp-hero__read" aria-hidden="true">
        <span class="lp-hero__read-cap">{{ metaText(section, 'scoreCaption') }}</span>
        <b class="lp-hero__read-num" dir="ltr">
          <span>{{ score }}</span
          ><small>%</small>
        </b>
        <span class="lp-hero__read-tier">{{ tier?.name }}</span>
      </div>

      <div class="lp-hero__stage">
        <div class="lp-hero__seals" aria-hidden="true">
          <figure
            v-for="seal in sealTiers"
            :key="seal.key"
            class="lp-seal"
            :class="{ 'is-on': score >= seal.min }"
            :style="{ '--tc': seal.color }"
          >
            <img :src="seal.seal" alt="" width="116" height="116" />
            <figcaption>
              {{ seal.name }}
              <span class="lp-seal__range" :dir="seal.max === null ? undefined : 'ltr'">{{
                seal.range
              }}</span>
            </figcaption>
          </figure>
        </div>
        <div ref="trophyRef" class="lp-hero__trophy" :style="maskStyle">
          <div class="lp-hero__glass">
            <img
              :src="trophySrc"
              :srcset="trophySrcset"
              :sizes="TROPHY_SIZES"
              :alt="text(section.subtitle) || $t('landing.hero.trophyAlt')"
              :width="TROPHY_WIDTH"
              :height="TROPHY_HEIGHT"
              fetchpriority="high"
            />
            <span ref="shineRef" class="lp-hero__shine"></span>
          </div>
          <img
            class="lp-hero__reflect"
            :src="trophySrc"
            :srcset="trophySrcset"
            :sizes="TROPHY_SIZES"
            alt=""
            aria-hidden="true"
          />
        </div>
      </div>
    </div>
  </section>
</template>
