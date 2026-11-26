import { ref } from 'vue'

const NONE = -1

export function useActiveIndex(count) {
  const activeIndex = ref(NONE)

  function clamp(index) {
    const total = count()
    if (!total) return NONE
    return Math.min(Math.max(index, 0), total - 1)
  }

  function set(index) {
    activeIndex.value = clamp(index)
  }

  function step(delta) {
    if (activeIndex.value === NONE) {
      set(delta > 0 ? 0 : count() - 1)
      return
    }
    set(activeIndex.value + delta)
  }

  function wrap(delta) {
    const total = count()
    if (!total) return
    const from = activeIndex.value === NONE && delta < 0 ? 0 : activeIndex.value
    activeIndex.value = (from + delta + total) % total
  }

  function first() {
    set(0)
  }

  function last() {
    set(count() - 1)
  }

  function clear() {
    activeIndex.value = NONE
  }

  return { activeIndex, set, step, wrap, first, last, clear }
}
