<script setup>
import { computed } from 'vue'
import { useLandingText } from '@/composables/landing/useLandingText'
import LandingSectionHead from '@/components/landing/LandingSectionHead.vue'

const props = defineProps({
  section: { type: Object, required: true },
})

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
        <li v-for="item in partners" :key="item.id">
          <component
            :is="item.ctaUrl ? 'a' : 'span'"
            class="lp-partners__logo"
            :href="item.ctaUrl || undefined"
            :target="item.ctaUrl ? '_blank' : undefined"
            :rel="item.ctaUrl ? 'noopener noreferrer' : undefined"
          >
            <img :src="item.image.url" :alt="text(item.title)" loading="lazy" />
          </component>
        </li>
      </ul>
    </div>
  </section>
</template>
