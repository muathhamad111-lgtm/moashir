<script setup>
import { computed } from 'vue'
import { gaugeArc } from '@/utils/charts/arcs'

const WIDTH = 200
const ARC = Object.freeze({ cx: WIDTH / 2, cy: 100, radius: 84 })
const HUNDRED = 100

const props = defineProps({
  value: { type: [Number, String, null], default: null },
  caption: { type: String, required: true },
  valueText: { type: String, required: true },
  note: { type: String, default: '' },
})

const ratio = computed(() =>
  props.value === null || props.value === '' ? 0 : Number(props.value) / HUNDRED,
)
</script>

<template>
  <figure class="chart chart--gauge">
    <svg
      class="chart-gauge__arc"
      :viewBox="`0 0 ${WIDTH} ${ARC.cy + 8}`"
      role="img"
      :aria-label="`${caption}: ${valueText}`"
      focusable="false"
    >
      <path class="chart-gauge__track" :d="gaugeArc(ARC, 1)" />
      <path v-if="ratio > 0" class="chart-gauge__fill" :d="gaugeArc(ARC, ratio)" />
    </svg>
    <p class="chart-gauge__value">
      <bdi dir="ltr">{{ valueText }}</bdi>
    </p>
    <p v-if="note" class="chart-gauge__note">{{ note }}</p>
  </figure>
</template>
