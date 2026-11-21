import { onBeforeUnmount, onMounted, ref } from 'vue'

const FALLBACK_WIDTH = 560

// The live width of an element, for charts drawn in real pixels (crisp text at any size).
export function useElementWidth(target, fallback = FALLBACK_WIDTH) {
  const width = ref(fallback)
  let observer = null

  onMounted(() => {
    const element = target.value
    if (!element) return
    width.value = element.clientWidth || fallback
    if (typeof ResizeObserver === 'undefined') return
    observer = new ResizeObserver(([entry]) => {
      width.value = Math.round(entry.contentRect.width) || fallback
    })
    observer.observe(element)
  })

  onBeforeUnmount(() => observer?.disconnect())

  return width
}
