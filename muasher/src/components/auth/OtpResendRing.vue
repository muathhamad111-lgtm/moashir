<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  secondsLeft: { type: Number, required: true },
})

const RING_LENGTH = 100

// The cooldown length is the highest count seen since it last rose (a new challenge or a resend).
const total = ref(props.secondsLeft)

watch(
  () => props.secondsLeft,
  (seconds, before) => {
    if (seconds > before) total.value = seconds
  },
)

const offset = computed(() => {
  if (!total.value) return RING_LENGTH
  return RING_LENGTH * (1 - props.secondsLeft / total.value)
})
</script>

<template>
  <svg class="otp-resend-ring" viewBox="0 0 36 36" aria-hidden="true" focusable="false">
    <circle class="otp-resend-ring__track" cx="18" cy="18" r="15" />
    <circle
      class="otp-resend-ring__fill"
      cx="18"
      cy="18"
      r="15"
      :pathLength="RING_LENGTH"
      :stroke-dasharray="RING_LENGTH"
      :stroke-dashoffset="offset"
    />
  </svg>
</template>
