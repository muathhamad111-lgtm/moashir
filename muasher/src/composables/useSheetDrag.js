import { ref } from 'vue'

// How far (px) a bottom sheet must be pulled down by its handle to close.
export const SHEET_DISMISS_DISTANCE = 80

/**
 * Drag-to-close for a bottom sheet's handle: the panel follows the finger down (never up), and a
 * pull past SHEET_DISMISS_DISTANCE closes it; a shorter pull springs back. Keyboard and screen-reader
 * users close it with Esc or the close button, so the handle itself is decorative.
 */
export function useSheetDrag(panelRef, { onDismiss }) {
  const offset = ref(0)
  let startY = null

  function move(event) {
    if (startY === null) return
    offset.value = Math.max(0, event.clientY - startY)
    if (panelRef.value) panelRef.value.style.transform = `translateY(${offset.value}px)`
  }

  function end() {
    if (startY === null) return
    const dismissed = offset.value > SHEET_DISMISS_DISTANCE
    startY = null
    offset.value = 0
    if (panelRef.value) panelRef.value.style.transform = ''
    window.removeEventListener('pointermove', move)
    window.removeEventListener('pointerup', end)
    window.removeEventListener('pointercancel', end)
    if (dismissed) onDismiss()
  }

  function start(event) {
    startY = event.clientY
    window.addEventListener('pointermove', move)
    window.addEventListener('pointerup', end)
    window.addEventListener('pointercancel', end)
  }

  return { start, offset }
}
