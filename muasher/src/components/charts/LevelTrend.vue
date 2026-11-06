<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useChartDirection } from '@/composables/useChartDirection'
import { pointsAttr, scaleY, timelineX } from '@/utils/chartGeometry'
import { scorePercentText } from '@/utils/percentText'

const WIDTH = 560
const HEIGHT = 260
const PLOT = { left: 96, right: 24, top: 16, bottom: 36 }
const SCALE_MIN = 40
const FULL = 100

const props = defineProps({
  series: { type: Array, required: true },
  ranges: { type: Array, default: () => [] },
  caption: { type: String, required: true },
})

const { t, n, locale } = useI18n()
const { isRtl } = useChartDirection()
const plotWidth = WIDTH - PLOT.left - PLOT.right
const plotHeight = HEIGHT - PLOT.top - PLOT.bottom
const scale = { top: PLOT.top, height: plotHeight, min: SCALE_MIN, max: FULL }
const axisX = computed(() => (isRtl.value ? WIDTH - PLOT.left + 8 : PLOT.left - 8))
const plotStart = computed(() => (isRtl.value ? PLOT.right : PLOT.left))

const bands = computed(() =>
  props.ranges.map((range, index) => {
    const upper = props.ranges[index + 1]?.min ?? FULL
    const top = scaleY(upper, scale)
    return {
      level: range.level,
      y: top,
      height: scaleY(range.min, scale) - top,
      labelY: scaleY(range.min, scale),
      label: `${t(`level.short.${range.level}`)} · ${scorePercentText(n, t, range.min)}`,
    }
  }),
)
const points = computed(() =>
  props.series.map((entry, index) => ({
    key: entry.id,
    x: plotStart.value + timelineX(index, props.series.length, isRtl.value, plotWidth),
    y: scaleY(entry.percent, scale),
    value: scorePercentText(n, t, entry.percent),
    date: new Intl.DateTimeFormat(locale.value, { year: 'numeric', month: '2-digit' }).format(
      new Date(entry.completedAt),
    ),
  })),
)
</script>

<template>
  <figure class="level-trend">
    <svg
      class="level-trend__svg"
      :viewBox="`0 0 ${WIDTH} ${HEIGHT}`"
      role="img"
      :aria-label="caption"
      focusable="false"
    >
      <g v-for="band in bands" :key="band.level">
        <rect
          class="level-trend__band"
          :class="`level-trend__band--${band.level}`"
          :x="plotStart"
          :y="band.y"
          :width="plotWidth"
          :height="band.height"
        />
        <text
          class="level-trend__band-label"
          :x="axisX"
          :y="band.labelY"
          :text-anchor="isRtl ? 'start' : 'end'"
          dominant-baseline="middle"
        >
          {{ band.label }}
        </text>
      </g>
      <polyline class="level-trend__line" :points="pointsAttr(points)" />
      <g v-for="point in points" :key="point.key">
        <circle class="level-trend__point" :cx="point.x" :cy="point.y" r="5" />
        <text class="level-trend__value" :x="point.x" :y="point.y - 12" text-anchor="middle">
          {{ point.value }}
        </text>
        <text class="level-trend__date" :x="point.x" :y="HEIGHT - 10" text-anchor="middle">
          {{ point.date }}
        </text>
      </g>
    </svg>
    <figcaption class="level-trend__caption">{{ caption }}</figcaption>
  </figure>
</template>
