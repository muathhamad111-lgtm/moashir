import { describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { SHEET_DISMISS_DISTANCE, useSheetDrag } from '@/composables/useSheetDrag'

function pointer(type, clientY) {
  const event = new Event(type)
  event.clientY = clientY
  return event
}

function drag(distance) {
  const panel = ref(document.createElement('div'))
  const onDismiss = vi.fn()
  const { start } = useSheetDrag(panel, { onDismiss })
  start({ clientY: 100 })
  window.dispatchEvent(pointer('pointermove', 100 + distance))
  const transform = panel.value.style.transform
  window.dispatchEvent(pointer('pointerup', 100 + distance))
  return { onDismiss, transform, panel }
}

describe('useSheetDrag', () => {
  it('lets the sheet follow the finger down', () => {
    expect(drag(30).transform).toBe('translateY(30px)')
  })

  it('never lifts the sheet above its place', () => {
    expect(drag(-50).transform).toBe('translateY(0px)')
  })

  it('springs back after a short pull', () => {
    const { onDismiss, panel } = drag(SHEET_DISMISS_DISTANCE - 10)
    expect(onDismiss).not.toHaveBeenCalled()
    expect(panel.value.style.transform).toBe('')
  })

  it('closes after a pull past the threshold', () => {
    expect(drag(SHEET_DISMISS_DISTANCE + 10).onDismiss).toHaveBeenCalledOnce()
  })
})
