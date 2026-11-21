<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { prefersReducedMotion } from '@/utils/motion'
import { clamp, easeInOut } from '@/utils/landing/easing'
import BrandLoaderMark from '@/components/feedback/BrandLoaderMark.vue'

const props = defineProps({
  done: { type: Boolean, default: false },
})

const emit = defineEmits(['finished'])

const MINIMUM_MS = 900
const WAITING_CAP = 0.88
const EXIT_DELAY_MS = 250
const REMOVE_DELAY_MS = 800

const progress = ref(0)
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
    progress.value = filled
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
    <BrandLoaderMark class="lp-loader__mark" :progress="progress" />
  </div>
</template>
