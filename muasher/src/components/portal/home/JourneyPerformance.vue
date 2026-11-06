<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { rankedCriteria } from '@/utils/accreditedPerformance'
import RankedBars from '@/components/charts/RankedBars.vue'
import RadarChart from '@/components/charts/RadarChart.vue'
import LevelTrend from '@/components/charts/LevelTrend.vue'

const props = defineProps({
  criteria: { type: Array, required: true },
  series: { type: Array, required: true },
  ranges: { type: Array, required: true },
  hasPrevious: { type: Boolean, default: false },
})

const { t } = useI18n()
const thresholds = computed(() => props.ranges.map((range) => range.min))
const ranked = computed(() => rankedCriteria(props.criteria))
const radarCaption = computed(() =>
  props.hasPrevious
    ? t('portal.home.performance.radarCaption')
    : t('portal.home.performance.radarCaptionSingle'),
)
</script>

<template>
  <div class="journey-performance">
    <section v-if="criteria.length" class="b-card" aria-labelledby="journey-ranked-title">
      <h2 id="journey-ranked-title" class="b-card__title">
        {{ t('portal.home.performance.ranked') }}
      </h2>
      <RankedBars
        :items="ranked"
        :thresholds="thresholds"
        :caption="t('portal.home.performance.rankedCaption')"
      />
    </section>
    <section v-if="criteria.length" class="b-card" aria-labelledby="journey-radar-title">
      <h2 id="journey-radar-title" class="b-card__title">
        {{ t('portal.home.performance.radar') }}
      </h2>
      <p class="b-card__legend">
        <span class="b-card__legend-item b-card__legend-item--current">
          {{ t('portal.home.performance.current') }}
        </span>
        <span v-if="hasPrevious" class="b-card__legend-item b-card__legend-item--previous">
          {{ t('portal.home.performance.previous') }}
        </span>
      </p>
      <RadarChart :items="criteria" :caption="radarCaption" />
    </section>
    <section
      v-if="series.length > 1"
      class="b-card b-card--wide"
      aria-labelledby="journey-trend-title"
    >
      <h2 id="journey-trend-title" class="b-card__title">
        {{ t('portal.home.performance.trend') }}
      </h2>
      <LevelTrend
        :series="series"
        :ranges="ranges"
        :caption="t('portal.home.performance.trendCaption')"
      />
    </section>
  </div>
</template>
