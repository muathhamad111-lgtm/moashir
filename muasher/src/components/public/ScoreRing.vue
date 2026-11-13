<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCountUp } from '@/composables/useCountUp'
import { formatNumber } from '@/utils/formatNumber'

const FULL = 100

const props = defineProps({
  value: { type: Number, required: true },
  label: { type: String, default: '' },
})

const { n, t } = useI18n()
const rootRef = ref(null)
const clamped = computed(() => Math.max(0, Math.min(FULL, props.value)))
const { current } = useCountUp(rootRef, () => clamped.value)
const shown = computed(() =>
  t('public.home.percent', { value: formatNumber(n, Math.round(current.value), 'integer') }),
)
const offset = computed(() => FULL - clamped.value)
</script>

<template>
  <div ref="rootRef" class="score-ring">
    <svg class="score-ring__svg" viewBox="0 0 120 120" aria-hidden="true" focusable="false">
      <circle class="score-ring__track" cx="60" cy="60" r="52" pathLength="100" />
      <circle
        class="score-ring__value"
        cx="60"
        cy="60"
        r="52"
        pathLength="100"
        stroke-dasharray="100 100"
        :stroke-dashoffset="offset"
      />
    </svg>
    <span class="score-ring__text">
      <bdi class="score-ring__number" dir="ltr">{{ shown }}</bdi>
      <span v-if="label" class="score-ring__label">{{ label }}</span>
    </span>
  </div>
</template>
