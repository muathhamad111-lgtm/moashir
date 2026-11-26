<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useLandingText } from '@/composables/landing/useLandingText'
import LandingSectionHead from '@/components/landing/LandingSectionHead.vue'

const props = defineProps({
  section: { type: Object, required: true },
})

const LOGO_WIDTH = 180
const LOGO_HEIGHT = 80

const { t } = useI18n()
const { text, heading } = useLandingText()
const head = computed(() => heading(props.section))
const partners = computed(() => (props.section.items ?? []).filter((item) => item.image?.url))
</script>

<template>
  <section v-if="partners.length" class="lp-sec lp-partners" aria-labelledby="lp-partners-title">
    <div class="lp-wrap">
      <LandingSectionHead
        center
        title-id="lp-partners-title"
        :eyebrow="head.eyebrow"
        :title="head.title"
        :highlight="head.highlight"
        :lede="head.lede"
      />
      <ul v-reveal class="lp-partners__row lp-reveal">
        <li v-for="item in partners" :key="item.id" class="lp-partners__item">
          <component
            :is="item.ctaUrl ? 'a' : 'span'"
            class="lp-partners__logo"
            :href="item.ctaUrl || undefined"
            :target="item.ctaUrl ? '_blank' : undefined"
            :rel="item.ctaUrl ? 'noopener noreferrer' : undefined"
          >
            <img
              :src="item.image.url"
              :alt="text(item.title)"
              :width="LOGO_WIDTH"
              :height="LOGO_HEIGHT"
              loading="lazy"
              decoding="async"
            />
            <span v-if="item.ctaUrl" class="lp-sr-only">{{ t('shell.opensInNewTab') }}</span>
          </component>
        </li>
      </ul>
    </div>
  </section>
</template>
