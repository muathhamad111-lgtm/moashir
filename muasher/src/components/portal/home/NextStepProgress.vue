<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatNumber } from '@/utils/formatNumber'

const props = defineProps({
  progress: { type: Object, required: true },
})

const { t, n } = useI18n()
const PERCENT = 100

const ratio = computed(() =>
  props.progress.subsTotal
    ? Math.round((props.progress.subsComplete / props.progress.subsTotal) * PERCENT)
    : 0,
)
const range = computed(() =>
  t('portal.home.range', {
    done: formatNumber(n, props.progress.subsComplete),
    total: formatNumber(n, props.progress.subsTotal),
  }),
)
</script>

<template>
  <div class="next-step-progress">
    <div class="next-step-progress__labels">
      <span id="next-step-progress-label" class="next-step-progress__label">{{
        t('portal.home.next.progressLabel')
      }}</span>
      <bdi class="next-step-progress__value" dir="ltr">{{ range }}</bdi>
    </div>
    <div
      class="next-step-progress__track"
      role="progressbar"
      aria-labelledby="next-step-progress-label"
      :aria-valuenow="progress.subsComplete"
      aria-valuemin="0"
      :aria-valuemax="progress.subsTotal"
      :aria-valuetext="range"
    >
      <svg class="next-step-progress__svg" focusable="false" aria-hidden="true">
        <rect class="next-step-progress__fill" :width="`${ratio}%`" height="100%" />
      </svg>
    </div>
  </div>
</template>
