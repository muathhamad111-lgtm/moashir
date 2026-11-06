<script setup>
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { clampPercent } from '@/utils/chartGeometry'
import { formatNumber } from '@/utils/formatNumber'
import { roundToOneDecimal } from '@/utils/percentText'

const FULL = 100
const TICKS = 40

const props = defineProps({
  value: { type: [Number, String], required: true },
  label: { type: String, required: true },
})

const { n, t } = useI18n()
const gradientId = useId()
const offset = computed(() => FULL - clampPercent(props.value))
const number = computed(() => formatNumber(n, roundToOneDecimal(clampPercent(props.value))))
const ticks = computed(() =>
  Array.from({ length: TICKS }, (_, index) => ({ key: index, angle: (360 / TICKS) * index })),
)
</script>

<template>
  <div class="level-ring">
    <svg class="level-ring__svg" viewBox="0 0 160 160" focusable="false" aria-hidden="true">
      <defs>
        <linearGradient :id="gradientId" x1="0" y1="0" x2="1" y2="1">
          <stop class="level-ring__stop-from" offset="0%" />
          <stop class="level-ring__stop-to" offset="100%" />
        </linearGradient>
      </defs>
      <line
        v-for="tick in ticks"
        :key="tick.key"
        class="level-ring__tick"
        x1="80"
        y1="4"
        x2="80"
        y2="9"
        :transform="`rotate(${tick.angle} 80 80)`"
      />
      <circle class="level-ring__track" cx="80" cy="80" r="62" pathLength="100" />
      <circle
        class="level-ring__value"
        cx="80"
        cy="80"
        r="62"
        pathLength="100"
        stroke-dasharray="100 100"
        :stroke-dashoffset="offset"
        :stroke="`url(#${gradientId})`"
      />
    </svg>
    <span class="level-ring__text">
      <bdi class="level-ring__figure" dir="ltr">
        <span class="level-ring__number">{{ number }}</span>
        <span class="level-ring__unit">{{ t('level.ring.unit') }}</span>
      </bdi>
      <span class="level-ring__label">{{ label }}</span>
    </span>
  </div>
</template>
