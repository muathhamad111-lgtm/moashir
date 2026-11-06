<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useChartDirection } from '@/composables/useChartDirection'
import { axisPercent, spanStart } from '@/utils/chartGeometry'
import { scorePercentText } from '@/utils/percentText'

const FULL = 100

const props = defineProps({
  ranges: { type: Array, required: true },
  percent: { type: [Number, String], default: null },
  pinLabelKey: { type: String, default: 'level.track.pin.entity' },
})

const { t, n } = useI18n()
const { isRtl } = useChartDirection()

const hasPin = computed(() => props.percent !== null && props.percent !== undefined)
const segments = computed(() => {
  const first = props.ranges[0]
  const lead = first
    ? [{ key: 'below', tier: 'neutral', from: 0, to: first.min, label: '', threshold: null }]
    : []
  return [
    ...lead,
    ...props.ranges.map((range, index) => ({
      key: range.level,
      tier: range.level,
      from: range.min,
      to: props.ranges[index + 1]?.min ?? FULL,
      label: t(`level.short.${range.level}`),
      threshold: range.min,
    })),
  ]
})
const segmentViews = computed(() =>
  segments.value.map((segment) => ({
    ...segment,
    x: `${spanStart(segment.from, segment.to, isRtl.value)}%`,
    width: `${segment.to - segment.from}%`,
    labelX: `${axisPercent((segment.from + segment.to) / 2, isRtl.value)}%`,
    thresholdX: `${axisPercent(segment.from, isRtl.value)}%`,
  })),
)
const pinX = computed(() => `${axisPercent(props.percent, isRtl.value)}%`)
</script>

<template>
  <div class="level-track">
    <svg class="level-track__svg" focusable="false" aria-hidden="true">
      <g v-for="segment in segmentViews" :key="segment.key">
        <text
          v-if="segment.label"
          class="level-track__name"
          :x="segment.labelX"
          y="12"
          text-anchor="middle"
        >
          {{ segment.label }}
        </text>
        <rect
          class="level-track__segment"
          :class="`level-track__segment--${segment.tier}`"
          :x="segment.x"
          y="20"
          :width="segment.width"
          height="8"
          rx="4"
        />
        <text
          v-if="segment.threshold !== null"
          class="level-track__threshold"
          :x="segment.thresholdX"
          y="46"
          text-anchor="middle"
          direction="ltr"
        >
          {{ scorePercentText(n, t, segment.threshold) }}
        </text>
      </g>
      <circle v-if="hasPin" class="level-track__pin" :cx="pinX" cy="24" r="7" />
    </svg>
    <i18n-t
      v-if="hasPin"
      :keypath="pinLabelKey"
      scope="global"
      tag="p"
      class="level-track__caption"
    >
      <template #value>
        <bdi dir="ltr">{{ scorePercentText(n, t, percent) }}</bdi>
      </template>
    </i18n-t>
  </div>
</template>
