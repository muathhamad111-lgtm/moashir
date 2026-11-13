import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { prefersReducedMotion } from '@/utils/motion'

const DURATION_MS = 1200
const VISIBLE_THRESHOLD = 0.35

function easeOut(ratio) {
  return 1 - Math.pow(1 - ratio, 3)
}

export function useCountUp(rootRef, target) {
  const current = ref(target())
  let observer = null
  let frameId = null

  function animate() {
    if (prefersReducedMotion() || typeof requestAnimationFrame !== 'function') {
      current.value = target()
      return
    }
    const start = performance.now()
    current.value = 0
    const step = (now) => {
      const ratio = Math.min(1, (now - start) / DURATION_MS)
      current.value = ratio < 1 ? target() * easeOut(ratio) : target()
      if (ratio < 1) frameId = requestAnimationFrame(step)
    }
    frameId = requestAnimationFrame(step)
  }

  function observe() {
    observer = new IntersectionObserver(
      (entries) => {
        if (!entries.some((entry) => entry.isIntersecting)) return
        observer.disconnect()
        observer = null
        animate()
      },
      { threshold: VISIBLE_THRESHOLD },
    )
    observer.observe(rootRef.value)
  }

  onMounted(() => {
    if (typeof IntersectionObserver !== 'function' || !rootRef.value || prefersReducedMotion())
      return
    observe()
  })

  watch(target, (value) => {
    current.value = value
  })

  onBeforeUnmount(() => {
    observer?.disconnect()
    if (frameId) cancelAnimationFrame(frameId)
  })

  return { current }
}
