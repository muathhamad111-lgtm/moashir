import { onBeforeUnmount, onMounted, ref } from 'vue'

const QUERY = '(prefers-reduced-motion: reduce)'

export function useReducedMotion() {
  const reduced = ref(false)
  let media = null

  function sync() {
    reduced.value = Boolean(media?.matches)
  }

  onMounted(() => {
    if (typeof window.matchMedia !== 'function') return
    media = window.matchMedia(QUERY)
    sync()
    media.addEventListener?.('change', sync)
  })

  onBeforeUnmount(() => media?.removeEventListener?.('change', sync))

  return { reduced }
}
