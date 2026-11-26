import { ref } from 'vue'
import { usePageScroll } from '@/composables/usePageScroll'

const READING_LINE = 0.35
const BOTTOM_TOLERANCE = 2

function reachedBottom() {
  const doc = document.documentElement
  return window.innerHeight + window.scrollY >= doc.scrollHeight - BOTTOM_TOLERANCE
}

export function useSectionSpy(ids) {
  const activeId = ref(null)

  function locate() {
    const present = ids().filter((id) => document.getElementById(id))
    if (!present.length) {
      activeId.value = null
      return
    }
    if (reachedBottom()) {
      activeId.value = present[present.length - 1]
      return
    }
    const line = window.innerHeight * READING_LINE
    const passed = present.filter(
      (id) => document.getElementById(id).getBoundingClientRect().top <= line,
    )
    activeId.value = passed.length ? passed[passed.length - 1] : present[0]
  }

  const { isScrolled, refresh } = usePageScroll(locate)

  return { activeId, isScrolled, refresh }
}
