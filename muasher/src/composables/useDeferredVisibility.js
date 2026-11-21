import { onScopeDispose, ref, toValue, watch } from 'vue'

export const SHOW_DELAY_MS = 300
export const MINIMUM_VISIBLE_MS = 500

// When one waiting page hands over to the next (the boot to a page's first data), the next
// shows at once instead of leaving a blank gap of one delay.
let lastHiddenAt = Number.NEGATIVE_INFINITY

const now = () => performance.now()

/**
 * Follows a loading flag with a short delay before showing (fast loads never flash) and a
 * minimum time once shown (a slow load never blinks).
 *
 * @param {import('vue').MaybeRefOrGetter<boolean>} isLoading
 * @returns {import('vue').Ref<boolean>}
 */
export function useDeferredVisibility(isLoading) {
  const visible = ref(false)
  let shownAt = 0
  let timer = null

  const clear = () => {
    clearTimeout(timer)
    timer = null
  }
  const show = () => {
    clear()
    visible.value = true
    shownAt = now()
  }
  const hide = () => {
    clear()
    if (visible.value) lastHiddenAt = now()
    visible.value = false
  }

  function follow(loading) {
    clear()
    if (loading && !visible.value) {
      const sinceLastHidden = now() - lastHiddenAt
      const isHandover = sinceLastHidden >= 0 && sinceLastHidden < SHOW_DELAY_MS
      if (isHandover) show()
      else timer = setTimeout(show, SHOW_DELAY_MS)
      return
    }
    if (!loading && visible.value) {
      timer = setTimeout(hide, Math.max(0, MINIMUM_VISIBLE_MS - (now() - shownAt)))
    }
  }

  watch(() => toValue(isLoading), follow, { immediate: true })
  onScopeDispose(hide)

  return visible
}
