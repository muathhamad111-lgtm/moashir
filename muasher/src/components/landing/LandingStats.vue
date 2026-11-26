<script setup>
import { useI18n } from 'vue-i18n'
import { useFeaturedKpis } from '@/composables/useFeaturedKpis'
import { useLandingText } from '@/composables/landing/useLandingText'
import { HOME_STATS_MAXIMUM } from '@/constants/kpis'
import LandingLink from '@/components/landing/LandingLink.vue'
import LandingStat from '@/components/landing/LandingStat.vue'

defineProps({
  section: { type: Object, required: true },
})

const { t } = useI18n()
const { text } = useLandingText()
const { featured } = useFeaturedKpis({ limit: HOME_STATS_MAXIMUM })
</script>

<template>
  <section class="lp-stats" :hidden="!featured.length" :aria-label="t('landing.stats.label')">
    <div class="lp-wrap">
      <ul v-reveal class="lp-stats__row lp-reveal">
        <LandingStat v-for="kpi in featured" :key="kpi.id" :kpi="kpi" />
      </ul>
      <p v-if="section.ctaUrl" v-reveal class="lp-stats__note lp-reveal">
        <LandingLink :to="section.ctaUrl">{{ text(section.ctaLabel) }}</LandingLink>
      </p>
    </div>
  </section>
</template>
