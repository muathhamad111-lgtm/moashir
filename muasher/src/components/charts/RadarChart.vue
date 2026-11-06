<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { localizedText } from '@/utils/localizedText'
import { clampPercent, pointsAttr, radarPoint } from '@/utils/chartGeometry'
import { splitLabel } from '@/utils/chartLabels'

const SIZE = 440
const CENTER = SIZE / 2
const RADIUS = 140
const LABEL_RADIUS = 1.32
const RINGS = [0.6, 0.8, 1]
const FULL = 100

const props = defineProps({
  items: { type: Array, required: true },
  caption: { type: String, required: true },
})

const { locale } = useI18n()
const geometry = { center: CENTER, radius: RADIUS }
const count = computed(() => props.items.length)

function polygonOf(values) {
  return pointsAttr(
    values.map((value, index) =>
      radarPoint(index, count.value, clampPercent(value) / FULL, geometry),
    ),
  )
}

const rings = computed(() =>
  RINGS.map((ratio) => ({
    ratio,
    points: pointsAttr(
      props.items.map((_, index) => radarPoint(index, count.value, ratio, geometry)),
    ),
  })),
)
const spokes = computed(() =>
  props.items.map((item, index) => ({
    key: item.key,
    ...radarPoint(index, count.value, 1, geometry),
  })),
)
const labels = computed(() =>
  props.items.map((item, index) => {
    const point = radarPoint(index, count.value, LABEL_RADIUS, geometry)
    return {
      key: item.key,
      x: point.x,
      y: point.y,
      lines: splitLabel(localizedText(item.name, locale.value) || item.code),
    }
  }),
)
const current = computed(() => polygonOf(props.items.map((item) => item.percent)))
const hasPrevious = computed(() => props.items.some((item) => item.previous !== null))
const previous = computed(() => polygonOf(props.items.map((item) => item.previous ?? 0)))
</script>

<template>
  <figure class="radar-chart">
    <svg
      class="radar-chart__svg"
      :viewBox="`0 0 ${SIZE} ${SIZE}`"
      role="img"
      :aria-label="caption"
      focusable="false"
    >
      <polygon
        v-for="ring in rings"
        :key="ring.ratio"
        class="radar-chart__ring"
        :points="ring.points"
      />
      <line
        v-for="spoke in spokes"
        :key="spoke.key"
        class="radar-chart__spoke"
        :x1="CENTER"
        :y1="CENTER"
        :x2="spoke.x"
        :y2="spoke.y"
      />
      <polygon v-if="hasPrevious" class="radar-chart__previous" :points="previous" />
      <polygon class="radar-chart__current" :points="current" />
      <text
        v-for="label in labels"
        :key="label.key"
        class="radar-chart__label"
        :x="label.x"
        :y="label.y"
        text-anchor="middle"
        dominant-baseline="middle"
      >
        <tspan
          v-for="(line, index) in label.lines"
          :key="line"
          :x="label.x"
          :dy="index === 0 ? `${(1 - label.lines.length) * 0.6}em` : '1.2em'"
        >
          {{ line }}
        </tspan>
      </text>
    </svg>
    <figcaption class="radar-chart__caption">{{ caption }}</figcaption>
  </figure>
</template>
