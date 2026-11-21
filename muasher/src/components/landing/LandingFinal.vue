<script setup>
import { computed } from 'vue'
import { useLandingText } from '@/composables/landing/useLandingText'
import LandingLink from '@/components/landing/LandingLink.vue'
import trophySmall from '@/assets/landing/trophy-sm.webp'

const props = defineProps({
  section: { type: Object, required: true },
})

const { text, metaText } = useLandingText()
const image = computed(() => props.section.image?.url || trophySmall)
</script>

<template>
  <section class="lp-final" data-lp-deep aria-labelledby="lp-final-title">
    <div class="lp-final__pattern" aria-hidden="true"></div>
    <div class="lp-wrap lp-final__in">
      <img
        v-reveal
        class="lp-final__trophy lp-reveal"
        :src="image"
        alt=""
        loading="lazy"
        width="132"
        height="212"
      />
      <div v-reveal class="lp-reveal">
        <span v-if="metaText(section, 'eyebrow')" class="lp-kicker">{{
          metaText(section, 'eyebrow')
        }}</span>
        <h2 id="lp-final-title">{{ text(section.title) }}</h2>
        <p v-if="text(section.description)">{{ text(section.description) }}</p>
      </div>
      <LandingLink
        v-if="section.ctaUrl"
        class="lp-btn lp-btn--fill lp-btn--lg"
        :to="section.ctaUrl"
      >
        {{ text(section.ctaLabel) }}
      </LandingLink>
    </div>
  </section>
</template>
