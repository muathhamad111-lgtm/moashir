<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useLandingText } from '@/composables/landing/useLandingText'
import { useLandingPrices } from '@/composables/landing/useLandingPrices'
import LandingSectionHead from '@/components/landing/LandingSectionHead.vue'
import LandingLink from '@/components/landing/LandingLink.vue'
import LandingPathCost from '@/components/landing/LandingPathCost.vue'

const props = defineProps({
  section: { type: Object, required: true },
})

const COMING_SOON = 'coming_soon'

const { t } = useI18n()
const { text, metaText, heading } = useLandingText()
const { pricingOf } = useLandingPrices()
const head = computed(() => heading(props.section))
const cards = computed(() =>
  (props.section.items ?? []).map((item, index) => {
    const path = item.meta?.path ?? ''
    const pricing = pricingOf(path)
    return {
      item,
      path,
      pricing,
      isFeatured: index === 0,
      isSoon: pricing?.availability === COMING_SOON || item.meta?.availability === COMING_SOON,
    }
  }),
)
</script>

<template>
  <section class="lp-sec lp-deep lp-paths" data-lp-deep aria-labelledby="lp-paths-title">
    <div class="lp-deep-pattern" aria-hidden="true"></div>
    <div class="lp-wrap">
      <LandingSectionHead
        title-id="lp-paths-title"
        :eyebrow="head.eyebrow"
        :title="head.title"
        :highlight="head.highlight"
        :lede="head.lede"
      />
      <div class="lp-paths__row">
        <article
          v-for="card in cards"
          :key="card.item.id"
          v-reveal
          class="lp-path lp-reveal"
          :class="{ 'lp-path--featured': card.isFeatured }"
          :data-path="card.path"
        >
          <div class="lp-path__top">
            <svg viewBox="0 0 24 24" aria-hidden="true"><use :href="`#lp-i-${card.path}`" /></svg>
            <span v-if="card.isSoon" class="lp-path__badge">{{ t('landing.paths.soon') }}</span>
            <span v-else-if="card.pricing?.isFree" class="lp-path__badge">
              {{ metaText(section, 'freeLabel') }}
            </span>
          </div>
          <h3>{{ text(card.item.title) }}</h3>
          <p class="lp-path__brief">{{ text(card.item.subtitle) }}</p>
          <p class="lp-path__idea">
            <b>{{ metaText(section, 'ideaLabel') }}</b>
            {{ text(card.item.description) }}
          </p>
          <LandingPathCost
            :pricing="card.pricing"
            :label="metaText(section, 'costLabel')"
            :free-label="metaText(section, 'freeLabel')"
            :note="metaText(card.item, 'costNote')"
          />
          <LandingLink
            v-if="card.item.ctaUrl"
            class="lp-btn"
            :class="card.isFeatured ? 'lp-btn--fill' : 'lp-btn--line'"
            :to="card.item.ctaUrl"
          >
            {{ text(card.item.ctaLabel) }}
          </LandingLink>
        </article>
      </div>
    </div>
  </section>
</template>
