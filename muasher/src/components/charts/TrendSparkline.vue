<script setup>
import { computed } from 'vue'
import { useChartDirection } from '@/composables/useChartDirection'
import { pointsAttr, scaleY, timelineX } from '@/utils/chartGeometry'

const WIDTH = 100
const HEIGHT = 40
const PADDING = 4

const props = defineProps({
  values: { type: Array, required: true },
  onBand: { type: Boolean, default: false },
})

const { isRtl } = useChartDirection()
const points = computed(() =>
  props.values.map((value, index) => ({
    x: timelineX(index, props.values.length, isRtl.value, WIDTH),
    y: scaleY(value, { top: PADDING, height: HEIGHT - PADDING * 2 }),
  })),
)
const last = computed(() => points.value.at(-1))
</script>

<template>
  <svg
    v-if="values.length > 1"
    class="sparkline"
    :class="{ 'sparkline--on-band': onBand }"
    :viewBox="`0 0 ${WIDTH} ${HEIGHT}`"
    preserveAspectRatio="none"
    focusable="false"
    aria-hidden="true"
  >
    <polyline
      class="sparkline__line"
      :points="pointsAttr(points)"
      vector-effect="non-scaling-stroke"
    />
    <circle
      class="sparkline__dot"
      :cx="last.x"
      :cy="last.y"
      r="2"
      vector-effect="non-scaling-stroke"
    />
  </svg>
</template>
