<script setup>
import { computed } from 'vue'
import { useLandingText } from '@/composables/landing/useLandingText'
import LandingLink from '@/components/landing/LandingLink.vue'

const props = defineProps({
  section: { type: Object, required: true },
})

const { text, firstWithRole } = useLandingText()
const secondary = computed(() => firstWithRole(props.section, 'secondary-cta'))
</script>

<template>
  <section class="lp-band" aria-labelledby="lp-band-title">
    <div class="lp-wrap">
      <div v-reveal class="lp-band__box lp-reveal" data-lp-deep>
        <svg class="lp-band__arrow" viewBox="0 0 100 100" aria-hidden="true">
          <use href="#lp-i-arrow" />
        </svg>
        <svg class="lp-band__arrow lp-band__arrow--two" viewBox="0 0 100 100" aria-hidden="true">
          <use href="#lp-i-arrow" />
        </svg>
        <div class="lp-band__text">
          <h2 id="lp-band-title">{{ text(section.title) }}</h2>
          <p v-if="text(section.description)">{{ text(section.description) }}</p>
        </div>
        <div class="lp-cta-pair">
          <LandingLink v-if="section.ctaUrl" class="lp-btn lp-btn--fill" :to="section.ctaUrl">
            {{ text(section.ctaLabel) }}
          </LandingLink>
          <LandingLink v-if="secondary?.ctaUrl" class="lp-btn lp-btn--line" :to="secondary.ctaUrl">
            {{ text(secondary.ctaLabel) }}
          </LandingLink>
        </div>
      </div>
    </div>
  </section>
</template>
