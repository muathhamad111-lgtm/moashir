import { onBeforeUnmount, onMounted, ref } from 'vue'

const DEFAULT_THRESHOLD = 0.2

export function useInView(targetRef, { threshold = DEFAULT_THRESHOLD } = {}) {
  const supported = typeof IntersectionObserver === 'function'
  const inView = ref(!supported)
  let observer = null

  onMounted(() => {
    if (!supported || !targetRef.value) return
    observer = new IntersectionObserver(
      (entries) => {
        inView.value = entries.some((entry) => entry.isIntersecting)
      },
      { threshold },
    )
    observer.observe(targetRef.value)
  })

  onBeforeUnmount(() => observer?.disconnect())

  return { inView }
}
