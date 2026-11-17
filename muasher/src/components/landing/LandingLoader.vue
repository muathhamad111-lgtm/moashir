<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { prefersReducedMotion } from '@/utils/motion'
import { clamp, easeInOut } from '@/utils/landing/easing'

const props = defineProps({
  done: { type: Boolean, default: false },
})

const emit = defineEmits(['finished'])

const MINIMUM_MS = 900
const WAITING_CAP = 0.88
const EXIT_DELAY_MS = 250
const REMOVE_DELAY_MS = 800
const STAR_PATH =
  'M12.82 92.5L0 86.09L12.78 79.71L17.84 77.19L32.72 69.75L40.44 65.87L57.41 57.41L65.83 40.54L68.79 34.68L77.23 17.73L77.25 17.73L78.45 15.31L86.09 0L93.74 15.29L94.92 17.65L103.38 34.63L106.25 40.32L114.78 57.41L131.67 65.86L139.57 69.8L154.16 77.09L159.51 79.75L172.21 86.09L159.45 92.46L154.06 95.18L139.39 102.5L131.39 106.5L114.78 114.82L106.3 131.77L103.44 137.48L94.94 154.54L93.76 156.86L86.09 172.21L78.43 156.86L65.77 131.53L57.41 114.82L40.82 106.52L32.88 102.52L17.95 95.08Z'

const offset = ref(100)
const leaving = ref(false)
const shown = ref(true)
let frame = 0
let timers = []

function finish() {
  leaving.value = true
  emit('finished')
  timers.push(setTimeout(() => (shown.value = false), REMOVE_DELAY_MS))
}

function run(started) {
  const step = (now) => {
    const ratio = clamp((now - started) / MINIMUM_MS)
    const filled = props.done ? easeInOut(ratio) : Math.min(WAITING_CAP, easeInOut(ratio))
    offset.value = 100 - filled * 100
    if (filled < 1) {
      frame = requestAnimationFrame(step)
      return
    }
    timers.push(setTimeout(finish, EXIT_DELAY_MS))
  }
  frame = requestAnimationFrame(step)
}

onMounted(() => {
  if (!prefersReducedMotion()) {
    run(performance.now())
    return
  }
  shown.value = false
  if (props.done) emit('finished')
  else {
    const stop = watch(
      () => props.done,
      (value) => {
        if (!value) return
        stop()
        emit('finished')
      },
    )
  }
})

onBeforeUnmount(() => {
  cancelAnimationFrame(frame)
  timers.forEach(clearTimeout)
})
</script>

<template>
  <div v-if="shown" class="lp-loader" :class="{ 'is-leaving': leaving }" aria-hidden="true">
    <svg class="lp-loader__mark" viewBox="0 0 200 200">
      <circle class="lp-loader__track" cx="100" cy="100" r="92" />
      <circle
        class="lp-loader__ring"
        cx="100"
        cy="100"
        r="92"
        pathLength="100"
        :stroke-dashoffset="offset.toFixed(2)"
      />
      <g class="lp-loader__spin">
        <path
          class="lp-loader__star"
          pathLength="100"
          transform="translate(100 100) scale(0.62) translate(-86.1 -86.1)"
          :d="STAR_PATH"
        />
      </g>
    </svg>
  </div>
</template>
