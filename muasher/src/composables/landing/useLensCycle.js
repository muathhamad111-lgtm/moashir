import { onBeforeUnmount, onMounted, ref } from 'vue'
import { prefersReducedMotion } from '@/utils/motion'

const INTERVAL_MS = 4800
const VISIBLE_RATIO = 0.35

export function useLensCycle(sectionRef, count) {
  const active = ref(0)
  let touched = false
  let visible = false
  let timer = 0
  let observer = null

  function select(index) {
    touched = true
    active.value = index
  }

  function rotate() {
    if (touched || !visible || document.hidden || !count()) return
    active.value = (active.value + 1) % count()
  }

  onMounted(() => {
    if (prefersReducedMotion() || typeof IntersectionObserver !== 'function') return
    observer = new IntersectionObserver(
      (entries) => {
        visible = entries[0]?.isIntersecting ?? false
      },
      { threshold: VISIBLE_RATIO },
    )
    observer.observe(sectionRef.value)
    timer = setInterval(rotate, INTERVAL_MS)
  })

  onBeforeUnmount(() => {
    clearInterval(timer)
    observer?.disconnect()
  })

  return { active, select }
}
