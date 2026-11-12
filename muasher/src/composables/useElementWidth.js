import { onBeforeUnmount, onMounted, ref } from 'vue'

export function useElementWidth(target, fallback) {
  const width = ref(fallback)
  let observer = null

  function measure(entries) {
    const measured = Math.round(entries[0]?.contentRect?.width ?? 0)
    if (measured > 0) width.value = measured
  }

  onMounted(() => {
    if (!target.value || typeof ResizeObserver === 'undefined') return
    observer = new ResizeObserver(measure)
    observer.observe(target.value)
  })

  onBeforeUnmount(() => observer?.disconnect())

  return width
}
