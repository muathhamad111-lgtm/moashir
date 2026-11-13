<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useChartDirection } from '@/composables/useChartDirection'
import { axisPercent, spanStart } from '@/utils/chartGeometry'
import { scorePercentText } from '@/utils/percentText'
import { localizedText } from '@/utils/localizedText'
import DeltaChip from './DeltaChip.vue'

const AXIS = [0, 25, 50, 75, 100]
const MAJOR_AXIS = [0, 50, 100]

const props = defineProps({
  items: { type: Array, required: true },
  thresholds: { type: Array, default: () => [] },
  caption: { type: String, required: true },
})

const { t, n, locale } = useI18n()
const { isRtl } = useChartDirection()
const rows = computed(() =>
  props.items.map((item) => ({
    ...item,
    label: localizedText(item.name, locale.value) || item.code,
    x: `${spanStart(0, item.percent, isRtl.value)}%`,
    width: `${item.percent}%`,
  })),
)
const ticks = computed(() =>
  props.thresholds.map((value) => ({ value, x: `${axisPercent(value, isRtl.value)}%` })),
)
const axis = computed(() =>
  AXIS.map((value) => ({
    value,
    x: `${axisPercent(value, isRtl.value)}%`,
    isMajor: MAJOR_AXIS.includes(value),
  })),
)
</script>

<template>
  <figure class="ranked-bars">
    <figcaption class="ranked-bars__caption">{{ caption }}</figcaption>
    <ol class="ranked-bars__list">
      <li v-for="row in rows" :key="row.key" class="ranked-bars__row">
        <span class="ranked-bars__label">{{ row.label }}</span>
        <svg class="ranked-bars__bar" focusable="false" aria-hidden="true">
          <rect class="ranked-bars__track" x="0" y="4" width="100%" height="8" rx="4" />
          <rect class="ranked-bars__fill" :x="row.x" y="4" :width="row.width" height="8" rx="4" />
          <line
            v-for="tick in ticks"
            :key="tick.value"
            class="ranked-bars__tick"
            :x1="tick.x"
            :x2="tick.x"
            y1="0"
            y2="16"
          />
        </svg>
        <bdi class="ranked-bars__value" dir="ltr">{{ scorePercentText(n, t, row.percent) }}</bdi>
        <DeltaChip class="ranked-bars__delta" :value="row.delta" />
      </li>
    </ol>
    <div class="ranked-bars__axis" aria-hidden="true">
      <span class="ranked-bars__axis-spacer"></span>
      <svg class="ranked-bars__axis-scale" focusable="false">
        <text
          v-for="mark in axis"
          :key="mark.value"
          class="ranked-bars__axis-label"
          :class="{ 'ranked-bars__axis-label--minor': !mark.isMajor }"
          :x="mark.x"
          y="12"
          text-anchor="middle"
        >
          {{ mark.value }}
        </text>
      </svg>
      <span class="ranked-bars__axis-spacer"></span>
      <span class="ranked-bars__axis-spacer"></span>
    </div>
  </figure>
</template>
