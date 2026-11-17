<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { CARD_FORMATS } from '@/constants/dashboardBoards'
import { DELTA_DIRECTIONS } from '@/constants/dashboardOverview'
import { deltaOf } from '@/utils/charts/delta'
import { formatNumber } from '@/utils/formatNumber'

const ICONS = Object.freeze({
  [DELTA_DIRECTIONS.UP]: 'arrow-up',
  [DELTA_DIRECTIONS.DOWN]: 'arrow-down',
})

const props = defineProps({
  value: { type: [Number, String, null], default: null },
  previous: { type: [Number, String, null], default: null },
  format: { type: String, required: true },
})

const { t, n } = useI18n()
const delta = computed(() =>
  deltaOf(props.value, props.previous, { inPoints: props.format === CARD_FORMATS.PERCENT }),
)

function signed(number) {
  const text = formatNumber(n, Math.abs(number).toFixed(1))
  return number > 0 ? `+${text}` : number < 0 ? `−${text}` : text
}

const text = computed(() => {
  const change = delta.value
  if (!change) return ''
  if (change.direction === DELTA_DIRECTIONS.FLAT) return t('dashboard.overview.delta.flat')
  if (change.points !== null)
    return t('dashboard.overview.delta.points', { value: signed(change.points) })
  if (change.ratio !== null)
    return t('dashboard.overview.delta.ratio', { value: signed(change.ratio) })
  return t('dashboard.overview.delta.fromZero')
})
</script>

<template>
  <span
    v-if="delta"
    class="overview-delta"
    :class="`overview-delta--${delta.direction}`"
    :data-direction="delta.direction"
  >
    <AppIcon v-if="ICONS[delta.direction]" :name="ICONS[delta.direction]" :mirror="false" />
    <bdi dir="ltr">{{ text }}</bdi>
    <span class="overview-delta__context">{{ t('dashboard.overview.delta.context') }}</span>
  </span>
</template>
