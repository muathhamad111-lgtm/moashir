<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useLandingText } from '@/composables/landing/useLandingText'
import { formatPadded } from '@/utils/formatNumber'
import LandingSectionHead from '@/components/landing/LandingSectionHead.vue'
import LandingGlyph from '@/components/landing/LandingGlyph.vue'

const props = defineProps({
  section: { type: Object, required: true },
})

const { n } = useI18n()
const { text, heading } = useLandingText()
const head = computed(() => heading(props.section))
</script>

<template>
  <section class="lp-sec lp-model" aria-labelledby="lp-model-title">
    <div class="lp-wrap">
      <LandingSectionHead
        center
        title-id="lp-model-title"
        :eyebrow="head.eyebrow"
        :title="head.title"
        :highlight="head.highlight"
        :lede="head.lede"
      />
      <ol class="lp-cycle">
        <li
          v-for="(item, index) in section.items"
          :key="item.id"
          v-reveal
          class="lp-cycle__step lp-reveal lp-draw"
          :style="{ '--i': index }"
        >
          <span class="lp-cycle__icon"><LandingGlyph :name="item.meta?.glyph" /></span>
          <span class="lp-cycle__num" dir="ltr" aria-hidden="true">{{
            formatPadded(n, index + 1)
          }}</span>
          <b>{{ text(item.title) }}</b>
          <p>{{ text(item.description) }}</p>
        </li>
      </ol>
    </div>
  </section>
</template>
