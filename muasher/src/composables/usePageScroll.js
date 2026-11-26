import { onBeforeUnmount, onMounted, ref } from 'vue'

const SCROLLED_OFFSET = 8

export function usePageScroll(onFrame = () => {}) {
  const isScrolled = ref(false)
  let frame = null

  function measure() {
    frame = null
    isScrolled.value = window.scrollY > SCROLLED_OFFSET
    onFrame()
  }

  function onScroll() {
    if (frame === null) frame = requestAnimationFrame(measure)
  }

  onMounted(() => {
    measure()
    window.addEventListener('scroll', onScroll, { passive: true })
  })

  onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll)
    if (frame !== null) cancelAnimationFrame(frame)
  })

  return { isScrolled, refresh: measure }
}
