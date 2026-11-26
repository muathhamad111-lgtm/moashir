import { computed, onBeforeUnmount, ref } from 'vue'
import { prefersReducedMotion } from '@/utils/motion'
import { clamp } from '@/utils/landing/easing'
import { FULL_SCORE, tierOfScore } from '@/utils/landing/tiers'

const SETTLE = 0.05
const EASE_IDLE = 0.11
const EASE_DRAG = 0.28

export function useLevelDial(tiers, initial) {
  const current = ref(initial())
  const dragging = ref(false)
  let target = current.value
  let frame = 0

  const value = computed(() => Math.round(current.value))
  const tier = computed(() => tierOfScore(tiers.value, value.value))

  function step() {
    const delta = target - current.value
    current.value =
      Math.abs(delta) < SETTLE
        ? target
        : current.value + delta * (dragging.value ? EASE_DRAG : EASE_IDLE)
    frame = current.value === target ? 0 : requestAnimationFrame(step)
  }

  function go(next) {
    target = clamp(Math.round(Number(next)), 0, FULL_SCORE)
    if (prefersReducedMotion()) {
      current.value = target
      return
    }
    if (!frame) frame = requestAnimationFrame(step)
  }

  onBeforeUnmount(() => cancelAnimationFrame(frame))

  return { current, value, tier, dragging, go }
}
