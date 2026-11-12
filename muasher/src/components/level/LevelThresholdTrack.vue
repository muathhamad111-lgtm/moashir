<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useChartDirection } from '@/composables/useChartDirection'
import { LEVEL_TRACK_LAYOUT } from '@/constants/levelHeroMotion'
import { nextLevelOf } from '@/utils/levelProgress'
import { scorePercentText } from '@/utils/percentText'
import { currentSegmentKey, levelAxis, splitSegment, trackSegments } from '@/utils/levelTrackAxis'

const FULL = 100
const HALF_GAP = LEVEL_TRACK_LAYOUT.gap / 2

const props = defineProps({
  ranges: { type: Array, required: true },
  percent: { type: [Number, String], default: null },
  pinValue: { type: [Number, String], default: null },
})

const { t, n } = useI18n()
const { isRtl } = useChartDirection()

const hasScore = computed(() => props.percent !== null && props.percent !== undefined)
const axis = computed(() => levelAxis(props.ranges))
const segments = computed(() => trackSegments(props.ranges))
const currentKey = computed(() => currentSegmentKey(segments.value, props.percent))
const nextThreshold = computed(() =>
  hasScore.value ? (nextLevelOf(props.ranges, props.percent)?.min ?? null) : props.ranges[0]?.min,
)

function mirrored(position) {
  return isRtl.value ? FULL - position : position
}

function pieceOf([from, to], segment, tier) {
  const start = axis.value(from) + (from === segment.from && from > 0 ? HALF_GAP : 0)
  const end = axis.value(to) - (to === segment.to && to < FULL ? HALF_GAP : 0)
  const left = isRtl.value ? FULL - end : start
  return {
    key: `${segment.key}-${from}`,
    tier,
    x: `${left}%`,
    width: `${Math.max(0, end - start)}%`,
  }
}

const pieces = computed(() =>
  segments.value.map((segment, index) => ({
    segment,
    ...splitSegment(segment, props.percent, index === segments.value.length - 1),
  })),
)
const ahead = computed(() =>
  pieces.value
    .filter((entry) => entry.ahead)
    .map((entry) => pieceOf(entry.ahead, entry.segment, entry.segment.tier)),
)
const reached = computed(() =>
  pieces.value
    .filter((entry) => entry.reached)
    .map((entry) => pieceOf(entry.reached, entry.segment, entry.segment.tier)),
)
const remaining = computed(() => {
  const holder = pieces.value.find((entry) => entry.remaining)
  return holder ? pieceOf(holder.remaining, holder.segment, holder.segment.tier) : null
})
const names = computed(() =>
  segments.value
    .filter((segment) => segment.threshold !== null)
    .map((segment) => ({
      key: segment.key,
      x: `${mirrored(axis.value((segment.from + segment.to) / 2))}%`,
      label: t(`level.short.${segment.tier}`),
      isCurrent: segment.key === currentKey.value,
    })),
)
const thresholds = computed(() =>
  props.ranges.map((range) => ({
    key: range.level,
    x: `${mirrored(axis.value(range.min))}%`,
    label: scorePercentText(n, t, range.min),
    isNext: range.min === nextThreshold.value,
  })),
)
const pinShare = computed(() => {
  const score = Number(props.percent)
  if (props.pinValue === null || props.pinValue === undefined || !(score > 0)) return 1
  return Math.min(1, Math.max(0, Number(props.pinValue) / score))
})
const pinX = computed(() => `${mirrored(axis.value(props.percent) * pinShare.value)}%`)
</script>

<template>
  <div class="level-track">
    <svg class="level-track__svg" focusable="false" aria-hidden="true">
      <text
        v-for="name in names"
        :key="`name-${name.key}`"
        class="level-track__name"
        :class="{ 'level-track__name--current': name.isCurrent }"
        :x="name.x"
        y="12"
        text-anchor="middle"
      >
        {{ name.label }}
      </text>
      <g class="level-track__base">
        <rect
          v-for="piece in ahead"
          :key="piece.key"
          class="level-track__segment"
          :class="`level-track__segment--${piece.tier}`"
          :x="piece.x"
          y="20"
          :width="piece.width"
          height="8"
          rx="4"
        />
      </g>
      <g class="level-track__reached">
        <rect
          v-for="piece in reached"
          :key="piece.key"
          class="level-track__segment"
          :class="`level-track__segment--${piece.tier}`"
          :x="piece.x"
          y="20"
          :width="piece.width"
          height="8"
          rx="4"
        />
      </g>
      <rect
        v-if="remaining"
        class="level-track__remaining"
        :x="remaining.x"
        y="18"
        :width="remaining.width"
        height="12"
        rx="4"
      />
      <text
        v-for="threshold in thresholds"
        :key="`threshold-${threshold.key}`"
        class="level-track__threshold"
        :class="{ 'level-track__threshold--next': threshold.isNext }"
        :x="threshold.x"
        y="46"
        text-anchor="middle"
        direction="ltr"
      >
        {{ threshold.label }}
      </text>
      <circle v-if="hasScore" class="level-track__pin" :cx="pinX" cy="24" r="7" />
    </svg>
  </div>
</template>
