import { onBeforeUnmount, onMounted, ref } from 'vue'
import { prefersReducedMotion } from '@/utils/motion'

const STEP_MS = 2600
const HOLD_MS = 3600
const RESTART_MS = 900
const FIRST_MS = 350
const VISIBLE_RATIO = 0.35
const NONE = -1

export function useKitStory(stageRef, lastStep) {
  const step = ref(NONE)
  let timer = 0
  let running = false
  let observer = null

  function durationOf(index) {
    return index === lastStep() ? HOLD_MS : STEP_MS
  }

  function advance() {
    if (!running) return
    if (step.value < lastStep()) {
      step.value += 1
      timer = setTimeout(advance, durationOf(step.value))
      return
    }
    step.value = NONE
    timer = setTimeout(advance, RESTART_MS)
  }

  function select(index) {
    clearTimeout(timer)
    step.value = index
    if (running) timer = setTimeout(advance, durationOf(index))
  }

  function onVisibility(entries) {
    const visible = entries[0]?.isIntersecting
    if (visible && !running) {
      running = true
      clearTimeout(timer)
      timer = setTimeout(advance, FIRST_MS)
    }
    if (!visible) {
      running = false
      clearTimeout(timer)
    }
  }

  onMounted(() => {
    if (prefersReducedMotion() || typeof IntersectionObserver !== 'function') {
      step.value = lastStep()
      return
    }
    observer = new IntersectionObserver(onVisibility, { threshold: VISIBLE_RATIO })
    observer.observe(stageRef.value)
  })

  onBeforeUnmount(() => {
    clearTimeout(timer)
    observer?.disconnect()
  })

  return { step, select, durationOf }
}
