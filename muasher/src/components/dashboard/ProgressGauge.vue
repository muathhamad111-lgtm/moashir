<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'

const props = defineProps({
  value: { type: Number, required: true },
  label: { type: String, default: '' },
  color: { type: String, default: 'primary' },
  suffix: { type: String, default: '%' },
})

const { n } = useI18n()

const COLORS = ['primary', 'success', 'info', 'warning', 'danger']

const gaugeColor = computed(() => (COLORS.includes(props.color) ? props.color : 'primary'))

const clamped = computed(() => Math.min(100, Math.max(0, Math.round(Number(props.value) || 0))))

const RADIUS = 52
const CIRCUMFERENCE = 2 * Math.PI * RADIUS

const dashOffset = computed(() => CIRCUMFERENCE * (1 - clamped.value / 100))

const displayValue = computed(() => `${formatNumber(n, clamped.value, 'default')}${props.suffix}`)
</script>

<template>
  <div class="gauge" :class="`gauge--${gaugeColor}`">
    <svg
      class="gauge__svg"
      viewBox="0 0 120 120"
      role="img"
      :aria-label="`${label} ${displayValue}`"
    >
      <circle class="gauge__track" cx="60" cy="60" :r="RADIUS" fill="none" />
      <circle
        class="gauge__fill"
        cx="60"
        cy="60"
        :r="RADIUS"
        fill="none"
        :stroke-dasharray="CIRCUMFERENCE"
        :stroke-dashoffset="dashOffset"
        stroke-linecap="round"
        transform="rotate(-90 60 60)"
      />
    </svg>
    <div class="gauge__center">
      <span class="gauge__value">{{ displayValue }}</span>
      <span v-if="label" class="gauge__label">{{ label }}</span>
    </div>
  </div>
</template>
