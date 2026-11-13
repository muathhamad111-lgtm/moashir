<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { scrollBehavior } from '@/utils/motion'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { HOME_ANCHORS } from '@/constants/homeSections'
import { useAuthStore } from '@/stores/auth'
import { useAmbientMotion } from '@/composables/useAmbientMotion'
import { useMotionControls } from '@/composables/useMotionControls'
import { splitWords } from '@/utils/splitWords'
import starMark from '@/assets/logos/moashir-star.svg'
import BaseButton from '@/components/ui/BaseButton.vue'
import BrandPattern from '@/components/ui/BrandPattern.vue'
import HeroPreviewCard from '@/components/public/HeroPreviewCard.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  section: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const router = useRouter()
const auth = useAuthStore()
const { t } = useI18n()
const { paused, toggle: toggleMotion } = useAmbientMotion()
const { isVisible: isMotionControlVisible } = useMotionControls()

const items = computed(() => props.section.items ?? [])
const secondaryCta = computed(
  () => items.value.find((item) => roleOf(item) === 'secondary-cta') ?? null,
)
const points = computed(() => items.value.filter((item) => roleOf(item) === 'point'))
const eyebrow = computed(() => props.localized(props.section.meta?.eyebrow) || t('app.name'))
const titleWords = computed(() => splitWords(props.localized(props.section.title)))
const highlightWords = computed(() => splitWords(props.localized(props.section.meta?.highlight)))
const lead = computed(
  () => props.localized(props.section.description) || props.localized(props.section.subtitle),
)
const preview = computed(() => props.section.meta?.preview ?? null)

function roleOf(item) {
  return String(item.meta?.role ?? '').replaceAll('_', '-')
}

function startAssessment() {
  router.push({
    name: auth.isAuthenticated ? ROUTE_NAMES.APP_DASHBOARD : ROUTE_NAMES.AUTH_REGISTER,
  })
}

function scrollToSecondary() {
  const url = secondaryCta.value?.ctaUrl ?? ''
  const anchor = url.startsWith('#') ? url.slice(1) : HOME_ANCHORS.paths
  document.getElementById(anchor)?.scrollIntoView({ behavior: scrollBehavior() })
}
</script>

<template>
  <section class="home-hero brand-surface" aria-labelledby="home-hero-title">
    <BrandPattern class="home-hero__pattern" variant="lattice" />
    <span class="home-hero__glow" aria-hidden="true"></span>
    <div class="home-hero__container">
      <div class="home-hero__copy">
        <span class="home-hero__eyebrow">
          <img class="home-hero__mark" :src="starMark" alt="" />
          {{ eyebrow }}
        </span>
        <h1 id="home-hero-title" class="home-hero__title">
          <span class="home-hero__line">
            <template v-for="(word, index) in titleWords" :key="`t-${index}`">
              <span class="home-hero__word">{{ word }}</span
              ><template v-if="index < titleWords.length - 1">{{ ' ' }}</template>
            </template>
          </span>
          <span v-if="highlightWords.length" class="home-hero__line home-hero__highlight">
            <template v-for="(word, index) in highlightWords" :key="`h-${index}`">
              <span class="home-hero__word">{{ word }}</span
              ><template v-if="index < highlightWords.length - 1">{{ ' ' }}</template>
            </template>
          </span>
        </h1>
        <p v-if="lead" class="home-hero__lead">{{ lead }}</p>
        <div class="home-hero__actions">
          <BaseButton variant="primary" size="xl" icon-end="arrow-right" @click="startAssessment">
            {{ localized(section.ctaLabel) }}
          </BaseButton>
          <BaseButton v-if="secondaryCta" variant="secondary" size="xl" @click="scrollToSecondary">
            {{ localized(secondaryCta.ctaLabel) }}
          </BaseButton>
        </div>
        <ul v-if="points.length" class="home-hero__points">
          <li v-for="point in points" :key="point.id" class="home-hero__point">
            <AppIcon name="check-circle" class="home-hero__point-icon" />
            {{ localized(point.title) }}
          </li>
        </ul>
      </div>
      <div v-if="preview" class="home-hero__media">
        <HeroPreviewCard :preview="preview" :localized="localized" />
        <button
          v-if="isMotionControlVisible"
          type="button"
          class="home-hero__motion"
          :aria-pressed="paused"
          @click="toggleMotion"
        >
          <AppIcon :name="paused ? 'play' : 'pause'" />
          {{ paused ? t('public.home.motion.play') : t('public.home.motion.pause') }}
        </button>
      </div>
    </div>
  </section>
</template>
