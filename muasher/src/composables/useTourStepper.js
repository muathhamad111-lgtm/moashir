import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { TOUR_STEP_MS } from '@/constants/homeSections'
import { prefersReducedMotion } from '@/utils/motion'

export function useTourStepper(count, { interval = TOUR_STEP_MS, visible = ref(true) } = {}) {
  const active = ref(0)
  const playing = ref(!prefersReducedMotion())
  const running = computed(() => playing.value && visible.value && count.value > 1)
  let timer = null
  let remaining = interval
  let startedAt = 0

  function clear() {
    if (timer) clearTimeout(timer)
    timer = null
  }

  function pause() {
    if (!timer) return
    clear()
    remaining = Math.max(0, remaining - (Date.now() - startedAt))
  }

  function resume() {
    clear()
    startedAt = Date.now()
    timer = setTimeout(next, remaining)
  }

  function next() {
    timer = null
    active.value = count.value ? (active.value + 1) % count.value : 0
    remaining = interval
    if (running.value) resume()
  }

  function select(index) {
    clear()
    active.value = index
    remaining = interval
    playing.value = false
  }

  function toggle() {
    playing.value = !playing.value
  }

  watch(running, (isRunning) => (isRunning ? resume() : pause()))
  onMounted(() => {
    if (running.value) resume()
  })
  onBeforeUnmount(clear)

  return { active, playing, running, select, toggle }
}
