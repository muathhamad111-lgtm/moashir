<script setup>
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'

const PERCENT = 100

const props = defineProps({
  label: { type: String, required: true },
  current: { type: Number, required: true },
  total: { type: Number, required: true },
})

const { t, n } = useI18n()
const labelId = useId()
const ratio = computed(() =>
  props.total ? Math.round((Math.min(props.current, props.total) / props.total) * PERCENT) : 0,
)
const range = computed(() =>
  t('portal.home.range', {
    done: formatNumber(n, props.current),
    total: formatNumber(n, props.total),
  }),
)
</script>

<template>
  <div class="progress-meter">
    <div class="progress-meter__labels">
      <span :id="labelId" class="progress-meter__label">{{ label }}</span>
      <bdi class="progress-meter__value" dir="ltr">{{ range }}</bdi>
    </div>
    <div
      class="progress-meter__track"
      role="progressbar"
      :aria-labelledby="labelId"
      :aria-valuenow="current"
      aria-valuemin="0"
      :aria-valuemax="total"
      :aria-valuetext="range"
    >
      <svg class="progress-meter__svg" focusable="false" aria-hidden="true">
        <rect class="progress-meter__fill" :width="`${ratio}%`" height="100%" />
      </svg>
    </div>
  </div>
</template>
