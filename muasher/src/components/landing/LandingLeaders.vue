<script setup>
import { useLandingText } from '@/composables/landing/useLandingText'

defineProps({
  section: { type: Object, required: true },
})

const { text, metaText } = useLandingText()
</script>

<template>
  <section class="lp-sec lp-leaders" aria-labelledby="lp-leaders-title">
    <div class="lp-wrap">
      <div v-reveal class="lp-leaders__box lp-reveal">
        <svg class="lp-leaders__arrow" viewBox="0 0 100 100" aria-hidden="true">
          <use href="#lp-i-arrow" />
        </svg>
        <div>
          <span v-if="metaText(section, 'eyebrow')" class="lp-kicker">{{
            metaText(section, 'eyebrow')
          }}</span>
          <h2 id="lp-leaders-title">
            {{ text(section.title) }}
            <span v-if="metaText(section, 'badgeLabel')" class="lp-leaders__soon">
              {{ metaText(section, 'badgeLabel') }}
            </span>
          </h2>
          <p>{{ text(section.description) }}</p>
        </div>
      </div>
    </div>
  </section>
</template>
