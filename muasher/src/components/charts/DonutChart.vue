<script setup>
import { computed } from 'vue'
import ChartTable from './ChartTable.vue'
import { ringSegment, ringSlices } from '@/utils/charts/arcs'

const SIZE = 180
const RING = Object.freeze({
  cx: SIZE / 2,
  cy: SIZE / 2,
  outer: SIZE / 2 - 2,
  inner: SIZE / 2 - 26,
})
const HUNDRED = 100

const props = defineProps({
  segments: { type: Array, required: true },
  caption: { type: String, required: true },
  categoryLabel: { type: String, required: true },
  valueLabel: { type: String, required: true },
  totalLabel: { type: String, required: true },
  formatValue: { type: Function, required: true },
  formatShare: { type: Function, required: true },
})

const total = computed(() => props.segments.reduce((sum, s) => sum + (Number(s.value) || 0), 0))
const slices = computed(() =>
  ringSlices(props.segments.map((s) => s.value)).map((slice, index) => ({
    ...slice,
    segment: props.segments[index],
  })),
)
const drawn = computed(() => slices.value.filter((slice) => slice.share > 0))
const tableRows = computed(() =>
  slices.value.map((slice) => [
    slice.segment.label,
    props.formatValue(slice.segment.value),
    props.formatShare(slice.share * HUNDRED),
  ]),
)
</script>

<template>
  <figure class="chart chart--donut">
    <div class="chart-donut">
      <svg
        class="chart-donut__ring"
        :viewBox="`0 0 ${SIZE} ${SIZE}`"
        role="img"
        :aria-label="caption"
        focusable="false"
      >
        <circle
          v-if="!drawn.length"
          class="chart-donut__empty"
          :cx="RING.cx"
          :cy="RING.cy"
          :r="(RING.outer + RING.inner) / 2"
        />
        <path
          v-for="slice in drawn"
          :key="slice.segment.key"
          class="chart-donut__slice"
          :class="slice.segment.toneClass"
          :style="slice.segment.color ? { '--chart-series': slice.segment.color } : undefined"
          :d="ringSegment(RING, slice.from, slice.share === 1 ? slice.to - 0.0001 : slice.to)"
        >
          <title>{{ slice.segment.label }}: {{ formatValue(slice.segment.value) }}</title>
        </path>
      </svg>
      <p class="chart-donut__total">
        <bdi class="chart-donut__total-value" dir="ltr">{{ formatValue(total) }}</bdi>
        <span class="chart-donut__total-label">{{ totalLabel }}</span>
      </p>
    </div>
    <ul class="chart-legend chart-legend--stacked">
      <li
        v-for="slice in slices"
        :key="slice.segment.key"
        class="chart-legend__item"
        :class="slice.segment.toneClass"
        :data-segment="slice.segment.key"
      >
        <span
          class="chart-legend__swatch"
          :style="slice.segment.color ? { '--chart-swatch': slice.segment.color } : undefined"
          aria-hidden="true"
        />
        <router-link
          v-if="slice.segment.to"
          class="chart-legend__label chart-legend__link"
          :to="slice.segment.to"
          :aria-label="slice.segment.linkLabel || undefined"
        >
          <bdi>{{ slice.segment.label }}</bdi>
        </router-link>
        <bdi v-else class="chart-legend__label">{{ slice.segment.label }}</bdi>
        <bdi class="chart-legend__value" dir="ltr">{{ formatValue(slice.segment.value) }}</bdi>
        <bdi class="chart-legend__share" dir="ltr">{{ formatShare(slice.share * HUNDRED) }}</bdi>
      </li>
    </ul>
    <ChartTable :caption="caption" :columns="[categoryLabel, valueLabel, '%']" :rows="tableRows" />
  </figure>
</template>
