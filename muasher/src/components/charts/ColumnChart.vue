<script setup>
import { computed, ref, toRef } from 'vue'
import ChartLegend from './ChartLegend.vue'
import ChartTable from './ChartTable.vue'
import ChartTooltip from './ChartTooltip.vue'
import { useElementWidth } from '@/composables/useElementWidth'
import { useChartHover } from '@/composables/useChartHover'
import {
  barLayout,
  bandCentre,
  columnPath,
  labelEvery,
  niceScale,
  plotFrame,
  valueY,
} from '@/utils/charts/scale'

const props = defineProps({
  categories: { type: Array, required: true },
  series: { type: Array, required: true },
  caption: { type: String, required: true },
  categoryLabel: { type: String, required: true },
  formatValue: { type: Function, required: true },
  formatTick: { type: Function, default: null },
  height: { type: Number, default: 240 },
  // Categorical axes (paths) label every column; time axes thin their labels out.
  labelAll: { type: Boolean, default: false },
})

const root = ref(null)
const width = useElementWidth(root)
const count = computed(() => props.categories.length)
const frame = computed(() => plotFrame(width.value, props.height))
const values = computed(() => props.series.flatMap((s) => s.values.map((v) => Number(v) || 0)))
const scale = computed(() => niceScale(Math.max(0, ...values.value)))
const band = computed(() => frame.value.innerWidth / Math.max(1, count.value))
const layout = computed(() => barLayout(band.value, props.series.length))
const every = computed(() => (props.labelAll ? 1 : labelEvery(count.value, frame.value.innerWidth)))
const baseline = computed(() => frame.value.top + frame.value.innerHeight)
const hover = useChartHover(toRef(() => count.value))

function centre(index) {
  return frame.value.left + bandCentre(index, count.value, frame.value.innerWidth)
}

function y(value) {
  return frame.value.top + valueY(value, scale.value.max, frame.value.innerHeight)
}

function barPath(seriesIndex, index) {
  const { width: barWidth, gap, group } = layout.value
  const x = centre(index) - group / 2 + seriesIndex * (barWidth + gap)
  return columnPath(x, y(props.series[seriesIndex].values[index]), barWidth, baseline.value)
}

function onPointer(event) {
  const bounds = event.currentTarget.getBoundingClientRect()
  hover.pointAt(Math.floor((event.clientX - bounds.left - frame.value.left) / band.value))
}

const legend = computed(() =>
  props.series.map((s) => ({ key: s.key, label: s.label, color: s.color })),
)
const tooltipRows = computed(() =>
  props.series.map((s) => ({
    key: s.key,
    label: s.label,
    color: s.color,
    value: props.formatValue(s.values[hover.activeIndex.value]),
  })),
)
const tableRows = computed(() =>
  props.categories.map((category, index) => [
    category,
    ...props.series.map((s) => props.formatValue(s.values[index])),
  ]),
)
</script>

<template>
  <figure ref="root" class="chart chart--columns">
    <ChartLegend v-if="series.length > 1" :items="legend" />
    <div
      class="chart__plot"
      tabindex="0"
      :aria-label="caption"
      @keydown="hover.onKey"
      @blur="hover.clear"
    >
      <svg :width="width" :height="height" dir="ltr" aria-hidden="true" focusable="false">
        <g class="chart__grid">
          <template v-for="tick in scale.ticks" :key="tick">
            <line :x1="frame.left" :x2="width - frame.right" :y1="y(tick)" :y2="y(tick)" />
            <text class="chart__tick" :x="frame.left - 8" :y="y(tick)" text-anchor="end">
              {{ (formatTick ?? formatValue)(tick) }}
            </text>
          </template>
        </g>
        <rect
          v-if="hover.isActive.value"
          class="chart__band"
          :x="centre(hover.activeIndex.value) - band / 2"
          :y="frame.top"
          :width="band"
          :height="frame.innerHeight"
        />
        <g v-for="(s, seriesIndex) in series" :key="s.key" :style="{ '--chart-series': s.color }">
          <path
            v-for="(category, index) in categories"
            :key="category"
            class="chart__bar"
            :d="barPath(seriesIndex, index)"
          />
        </g>
        <template v-for="(category, index) in categories" :key="category">
          <text
            v-if="index % every === 0"
            class="chart__tick"
            :x="centre(index)"
            :y="height - 8"
            text-anchor="middle"
          >
            {{ category }}
          </text>
        </template>
        <rect
          class="chart__hit"
          :width="width"
          :height="height"
          @pointermove="onPointer"
          @pointerleave="hover.clear"
        />
      </svg>
      <ChartTooltip
        v-if="hover.isActive.value"
        :title="categories[hover.activeIndex.value]"
        :rows="tooltipRows"
        :x="centre(hover.activeIndex.value)"
        :width="width"
      />
    </div>
    <ChartTable
      :caption="caption"
      :columns="[categoryLabel, ...series.map((s) => s.label)]"
      :rows="tableRows"
    />
  </figure>
</template>
