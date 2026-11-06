<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'

const props = defineProps({
  value: { type: Number, default: null },
  onBand: { type: Boolean, default: false },
})

const { t, n } = useI18n()
const direction = computed(() => {
  if (props.value > 0) return 'up'
  if (props.value < 0) return 'down'
  return 'flat'
})
const text = computed(() => formatNumber(n, Math.abs(props.value)))
const label = computed(() => t(`charts.delta.${direction.value}`, { value: text.value }))
</script>

<template>
  <span
    v-if="value !== null"
    class="delta-chip"
    :class="[`delta-chip--${direction}`, { 'delta-chip--on-band': onBand }]"
    :aria-label="label"
    role="img"
  >
    <span class="delta-chip__mark" aria-hidden="true">{{
      t(`charts.delta.mark.${direction}`)
    }}</span>
    <bdi dir="ltr" aria-hidden="true">{{ text }}</bdi>
  </span>
</template>
