<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { bandRanges, gapRanges } from '@/utils/insights'
import { GAP_TONES } from '@/constants/insights'

const props = defineProps({
  gaps: { type: Object, required: true },
  bands: { type: Object, required: true },
})

const { t, n } = useI18n()
const gapSegments = computed(() => gapRanges(props.gaps))
const bandSegments = computed(() => bandRanges(props.bands))

function sizeOf(segment) {
  return { '--segment-size': `${Math.max(0, segment.to - segment.from)}%` }
}
</script>

<template>
  <section class="rules-preview" aria-labelledby="rules-preview-title" data-testid="rules-preview">
    <h3 id="rules-preview-title" class="rules-preview__title">
      {{ t('settings.insights.previewTitle') }}
    </h3>
    <p class="rules-preview__label">{{ t('settings.insights.previewGaps') }}</p>
    <ol class="rules-preview__bar">
      <li
        v-for="segment in gapSegments"
        :key="segment.gapClass"
        class="rules-preview__segment"
        :class="`rules-preview__segment--${GAP_TONES[segment.gapClass]}`"
        :style="sizeOf(segment)"
      >
        <span class="rules-preview__name">{{ t(`insights.classes.${segment.gapClass}`) }}</span>
      </li>
    </ol>
    <p class="rules-preview__label">{{ t('settings.insights.previewBands') }}</p>
    <ol class="rules-preview__bar">
      <li
        v-for="segment in bandSegments"
        :key="segment.band"
        class="rules-preview__segment"
        :class="`rules-preview__segment--${segment.band}`"
        :style="sizeOf(segment)"
      >
        <span class="rules-preview__name">{{ t(`insights.bands.${segment.band}`) }}</span>
      </li>
    </ol>
    <p class="rules-preview__axis" aria-hidden="true">
      <bdi dir="ltr">{{ t('public.home.percent', { value: n(0) }) }}</bdi>
      <bdi dir="ltr">{{ t('public.home.percent', { value: n(100) }) }}</bdi>
    </p>
  </section>
</template>
