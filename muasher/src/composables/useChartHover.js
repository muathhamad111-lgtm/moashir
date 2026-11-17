import { computed, ref } from 'vue'

const KEY_STEPS = Object.freeze({ ArrowRight: 1, ArrowLeft: -1 })

// The hovered or keyboard-focused category of a chart drawn left to right.
export function useChartHover(count) {
  const activeIndex = ref(null)
  const isActive = computed(() => activeIndex.value !== null)

  function clamp(index) {
    return Math.min(Math.max(index, 0), Math.max(count.value - 1, 0))
  }

  function pointAt(index) {
    activeIndex.value = count.value ? clamp(index) : null
  }

  function onKey(event) {
    if (event.key === 'Escape') return clear()
    const step = KEY_STEPS[event.key]
    if (!step) return
    event.preventDefault()
    pointAt(activeIndex.value === null ? 0 : activeIndex.value + step)
  }

  function clear() {
    activeIndex.value = null
  }

  return { activeIndex, isActive, pointAt, onKey, clear }
}
