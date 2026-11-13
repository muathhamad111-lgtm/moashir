<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useChartDirection } from '@/composables/useChartDirection'
import { useElementWidth } from '@/composables/useElementWidth'
import { pointsAttr } from '@/utils/chartGeometry'
import { levelTrendLayout } from '@/utils/levelTrendLayout'
import { scorePercentText } from '@/utils/percentText'
import { formatDate } from '@/utils/datetime'

const FALLBACK_WIDTH = 640
const MONTH_FORMAT = Object.freeze({ month: 'short', year: 'numeric', timeZone: 'Asia/Riyadh' })
const LTR_ISOLATE = '\u2066'
const END_ISOLATE = '\u2069'

const props = defineProps({
  series: { type: Array, required: true },
  ranges: { type: Array, default: () => [] },
  caption: { type: String, required: true },
})

const { t, n, locale } = useI18n()

function isolatedPercent(value) {
  return `${LTR_ISOLATE}${scorePercentText(n, t, value)}${END_ISOLATE}`
}
const { isRtl } = useChartDirection()
const frame = ref(null)
const width = useElementWidth(frame, FALLBACK_WIDTH)
const textDirection = computed(() => (isRtl.value ? 'rtl' : 'ltr'))

const layout = computed(() =>
  levelTrendLayout({
    series: props.series,
    ranges: props.ranges,
    width: width.value,
    isRtl: isRtl.value,
  }),
)
const bands = computed(() =>
  layout.value.bands.map((band) => ({
    ...band,
    label: `${t(`level.short.${band.level}`)} · ${isolatedPercent(band.min)}`,
  })),
)
const points = computed(() =>
  layout.value.points.map((point) => ({
    ...point,
    value: scorePercentText(n, t, point.percent),
    date: formatDate(point.completedAt, locale.value, MONTH_FORMAT),
  })),
)
const floorLabel = computed(() => isolatedPercent(layout.value.min))
</script>

<template>
  <figure ref="frame" class="level-trend">
    <svg
      class="level-trend__svg"
      :width="layout.width"
      :height="layout.height"
      :viewBox="`0 0 ${layout.width} ${layout.height}`"
      role="img"
      :aria-label="caption"
      focusable="false"
    >
      <g v-for="band in bands" :key="band.level">
        <rect
          class="level-trend__band"
          :class="`level-trend__band--${band.level}`"
          :x="layout.plot.x"
          :y="band.y"
          :width="layout.plot.width"
          :height="band.height"
        />
        <line
          class="level-trend__threshold"
          :class="`level-trend__threshold--${band.level}`"
          :x1="layout.plot.x"
          :x2="layout.plot.x + layout.plot.width"
          :y1="band.y + band.height"
          :y2="band.y + band.height"
        />
        <text
          class="level-trend__band-label"
          :x="layout.labelX"
          :y="band.labelY"
          :direction="textDirection"
          text-anchor="start"
          dominant-baseline="middle"
        >
          {{ band.label }}
        </text>
      </g>
      <line
        class="level-trend__floor"
        :x1="layout.plot.x"
        :x2="layout.plot.x + layout.plot.width"
        :y1="layout.floorY"
        :y2="layout.floorY"
      />
      <text
        class="level-trend__floor-label"
        :x="layout.labelX"
        :y="layout.floorY"
        :direction="textDirection"
        text-anchor="start"
        dominant-baseline="middle"
      >
        {{ floorLabel }}
      </text>
      <polyline class="level-trend__line" :points="pointsAttr(points)" />
      <g v-for="point in points" :key="point.key">
        <circle
          class="level-trend__point"
          :class="{ 'level-trend__point--latest': point.isLatest }"
          :cx="point.x"
          :cy="point.y"
          r="6"
        />
        <text
          class="level-trend__value"
          :x="point.x"
          :y="point.valueY"
          direction="ltr"
          text-anchor="middle"
        >
          {{ point.value }}
        </text>
        <text
          class="level-trend__date"
          :x="point.x"
          :y="layout.dateY"
          :direction="textDirection"
          text-anchor="middle"
        >
          {{ point.date }}
        </text>
      </g>
    </svg>
    <figcaption class="level-trend__caption">{{ caption }}</figcaption>
  </figure>
</template>
