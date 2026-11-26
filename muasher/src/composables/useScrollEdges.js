import { onBeforeUnmount, onMounted, ref } from 'vue'

// Whether a sideways-scrolling box has more content toward its start or its end, in reading order
// (in a right-to-left page `scrollLeft` runs negative, so only its size counts).
export function useScrollEdges(boxRef) {
  const scrollable = ref(false)
  const moreAtStart = ref(false)
  const moreAtEnd = ref(false)
  let observer = null

  function measure() {
    const box = boxRef.value
    if (!box) return
    const range = box.scrollWidth - box.clientWidth
    const offset = Math.abs(box.scrollLeft)
    scrollable.value = range > 1
    moreAtStart.value = offset > 1
    moreAtEnd.value = range - offset > 1
  }

  onMounted(() => {
    measure()
    if (typeof ResizeObserver === 'undefined' || !boxRef.value) return
    observer = new ResizeObserver(measure)
    observer.observe(boxRef.value)
    if (boxRef.value.firstElementChild) observer.observe(boxRef.value.firstElementChild)
  })
  onBeforeUnmount(() => observer?.disconnect())

  return { scrollable, moreAtStart, moreAtEnd, measure }
}
