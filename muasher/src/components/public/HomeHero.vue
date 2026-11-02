<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { useAuthStore } from '@/stores/auth'
import BaseButton from '@/components/ui/BaseButton.vue'
import IllustrationSlot from '@/components/public/IllustrationSlot.vue'

const props = defineProps({
  section: { type: Object, required: true },
  localized: { type: Function, required: true },
})

const router = useRouter()
const auth = useAuthStore()
const { t } = useI18n()

const heroImage = computed(() => props.section.image || null)
const heroAlt = computed(() => props.localized(props.section.title) || t('illustrations.hero'))

const secondaryCta = computed(() => {
  const items = [...(props.section.items ?? [])].sort((a, b) => a.order - b.order)
  return items.find((item) => item.meta?.role === 'secondary-cta') ?? null
})

function startAssessment() {
  if (auth.isAuthenticated) {
    router.push({ name: ROUTE_NAMES.APP_DASHBOARD })
    return
  }
  router.push({ name: ROUTE_NAMES.AUTH_REGISTER })
}

function explorePaths() {
  const target = document.getElementById('evaluation-paths')
  if (target) target.scrollIntoView({ behavior: 'smooth' })
}
</script>

<template>
  <section id="hero" class="home-hero" aria-labelledby="home-hero-title">
    <div class="home-hero__inner">
      <span class="home-hero__eyebrow">{{ $t('app.name') }}</span>
      <h1 id="home-hero-title" class="home-hero__title">{{ localized(section.title) }}</h1>
      <p class="home-hero__subtitle">{{ localized(section.subtitle) }}</p>
      <p class="home-hero__support">{{ localized(section.description) }}</p>
      <div class="home-hero__actions">
        <BaseButton
          variant="primary"
          size="lg"
          icon="fa-solid fa-flag-checkered"
          @click="startAssessment"
        >
          {{ localized(section.ctaLabel) }}
        </BaseButton>
        <BaseButton
          v-if="secondaryCta"
          variant="secondary"
          size="lg"
          icon="fa-solid fa-arrow-down"
          @click="explorePaths"
        >
          {{ localized(secondaryCta.ctaLabel) }}
        </BaseButton>
      </div>
    </div>
    <div class="home-hero__media">
      <IllustrationSlot :src="heroImage" :alt="heroAlt" variant="hero" />
    </div>
  </section>
</template>
