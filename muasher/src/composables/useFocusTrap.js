import { nextTick, watch } from 'vue'

const FOCUSABLE =
  'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

export function useFocusTrap(containerRef, { isActive, onEscape }) {
  let previouslyFocused = null

  function focusables() {
    return Array.from(containerRef.value?.querySelectorAll(FOCUSABLE) ?? [])
  }

  function cycle(event) {
    const elements = focusables()
    if (!elements.length) {
      event.preventDefault()
      return
    }
    const first = elements[0]
    const last = elements[elements.length - 1]
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault()
      last.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault()
      first.focus()
    }
  }

  function onKeydown(event) {
    if (event.key === 'Escape') {
      event.stopPropagation()
      onEscape()
      return
    }
    if (event.key === 'Tab') cycle(event)
  }

  watch(isActive, async (active) => {
    if (active) {
      previouslyFocused = document.activeElement
      await nextTick()
      focusables()[0]?.focus()
      return
    }
    if (previouslyFocused instanceof HTMLElement) previouslyFocused.focus()
    previouslyFocused = null
  })

  return { onKeydown }
}
