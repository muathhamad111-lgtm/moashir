<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'

const props = defineProps({
  series: { type: Array, default: () => [] },
  localized: { type: Function, required: true },
  color: { type: String, default: 'primary' },
  unit: { type: String, default: '' },
})

const { n } = useI18n()

const COLORS = ['primary', 'success', 'info', 'warning', 'danger']

const barColor = computed(() => (COLORS.includes(props.color) ? props.color : 'primary'))

const max = computed(() => {
  const values = props.series.map((item) => Number(item.value) || 0)
  return Math.max(1, ...values)
})

function labelOf(item) {
  return props.localized(item.label ?? item.name)
}

function widthOf(item) {
  const value = Number(item.value) || 0
  return `${Math.max(2, Math.round((value / max.value) * 100))}%`
}

function valueOf(item) {
  return formatNumber(n, Number(item.value) || 0, 'default')
}
</script>

<template>
  <ul class="mini-bar" :class="`mini-bar--${barColor}`">
    <li v-for="(item, index) in series" :key="item.id ?? index" class="mini-bar__row">
      <span class="mini-bar__label">{{ labelOf(item) }}</span>
      <span class="mini-bar__track">
        <span
          class="mini-bar__fill"
          role="img"
          :aria-label="`${labelOf(item)}: ${valueOf(item)} ${unit}`"
          :style="{ inlineSize: widthOf(item) }"
        ></span>
      </span>
      <span class="mini-bar__value">{{ valueOf(item) }}<template v-if="unit"> {{ unit }}</template></span>
    </li>
  </ul>
</template>
