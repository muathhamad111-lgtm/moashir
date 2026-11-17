<script setup>
import { computed, ref, toRef } from 'vue'
import ChartLegend from './ChartLegend.vue'
import ChartTable from './ChartTable.vue'
import ChartTooltip from './ChartTooltip.vue'
import { useElementWidth } from '@/composables/useElementWidth'
import { useChartHover } from '@/composables/useChartHover'
import { labelEvery, niceScale, plotFrame, valueY } from '@/utils/charts/scale'
import { smoothPath } from '@/utils/charts/curves'

const props = defineProps({
  categories: { type: Array, required: true },
  series: { type: Array, required: true },
  caption: { type: String, required: true },
  categoryLabel: { type: String, required: true },
  formatValue: { type: Function, required: true },
  formatTick: { type: Function, default: null },
  height: { type: Number, default: 240 },
  area: { type: Boolean, default: false },
  // Series shown at first (all when empty); the legend buttons add or remove the rest.
  initialShown: { type: Array, default: () => [] },
})

const root = ref(null)
const width = useElementWidth(root)
const count = computed(() => props.categories.length)
const frame = computed(() => plotFrame(width.value, props.height))
const hidden = ref(
  props.initialShown.length
    ? props.series.map((s) => s.key).filter((key) => !props.initialShown.includes(key))
    : [],
)
const shown = computed(() => props.series.filter((s) => !hidden.value.includes(s.key)))
const scale = computed(() => niceScale(Math.max(0, ...shown.value.flatMap((s) => s.values))))
const every = computed(() => labelEvery(count.value, frame.value.innerWidth))
const hover = useChartHover(toRef(() => count.value))

function x(index) {
  const { left, innerWidth } = frame.value
  return count.value > 1 ? left + (index * innerWidth) / (count.value - 1) : left + innerWidth / 2
}

function y(value) {
  return frame.value.top + valueY(value, scale.value.max, frame.value.innerHeight)
}

function linePath(values) {
  return smoothPath(values.map((value, index) => [x(index), y(value)]))
}

function areaPath(values) {
  const baseline = frame.value.top + frame.value.innerHeight
  return `${linePath(values)} L${x(values.length - 1)},${baseline} L${x(0)},${baseline} Z`
}

// One series always stays on screen; hiding the last one is refused.
function toggle(key) {
  if (hidden.value.includes(key)) hidden.value = hidden.value.filter((item) => item !== key)
  else if (shown.value.length > 1) hidden.value = [...hidden.value, key]
}

function onPointer(event) {
  const bounds = event.currentTarget.getBoundingClientRect()
  const step = count.value > 1 ? frame.value.innerWidth / (count.value - 1) : 1
  hover.pointAt(Math.round((event.clientX - bounds.left - frame.value.left) / step))
}

const legend = computed(() =>
  props.series.map((s) => ({ key: s.key, label: s.label, color: s.color })),
)
const tooltipRows = computed(() =>
  shown.value.map((s) => ({
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
  <figure ref="root" class="chart chart--line">
    <ChartLegend
      v-if="series.length > 1"
      :items="legend"
      :hidden="hidden"
      toggleable
      @toggle="toggle"
    />
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
        <g v-for="s in shown" :key="s.key" :style="{ '--chart-series': s.color }">
          <path v-if="area || shown.length === 1" class="chart__area" :d="areaPath(s.values)" />
          <path class="chart__line" :d="linePath(s.values)" />
        </g>
        <template v-for="(category, index) in categories" :key="category">
          <text
            v-if="index % every === 0"
            class="chart__tick"
            :x="x(index)"
            :y="height - 8"
            text-anchor="middle"
          >
            {{ category }}
          </text>
        </template>
        <g v-if="hover.isActive.value" class="chart__focus">
          <line
            :x1="x(hover.activeIndex.value)"
            :x2="x(hover.activeIndex.value)"
            :y1="frame.top"
            :y2="frame.top + frame.innerHeight"
          />
          <circle
            v-for="s in shown"
            :key="s.key"
            class="chart__dot"
            :style="{ '--chart-series': s.color }"
            :cx="x(hover.activeIndex.value)"
            :cy="y(s.values[hover.activeIndex.value])"
            r="5"
          />
        </g>
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
        :x="x(hover.activeIndex.value)"
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
