import { nextTick, onBeforeUnmount, toValue, watch } from 'vue'
import { focusablesIn, trapTabKey } from '@/utils/a11y/focusables'

export function useFocusTrap(containerRef, { isActive, onEscape }) {
  let previouslyFocused = null

  function focusInitial() {
    const container = containerRef.value
    const preferred = container?.querySelector('[data-autofocus]')
    ;(preferred ?? focusablesIn(container)[0] ?? container)?.focus()
  }

  function restoreFocus() {
    if (previouslyFocused instanceof HTMLElement && previouslyFocused !== document.body) {
      previouslyFocused.focus()
    }
    previouslyFocused = null
  }

  function onKeydown(event) {
    if (event.key === 'Escape') {
      event.stopPropagation()
      onEscape()
      return
    }
    if (event.key === 'Tab') trapTabKey(event, containerRef.value)
  }

  watch(
    isActive,
    async (active) => {
      if (!active) {
        restoreFocus()
        return
      }
      previouslyFocused = document.activeElement
      await nextTick()
      focusInitial()
    },
    { immediate: true },
  )

  onBeforeUnmount(() => {
    if (toValue(isActive)) restoreFocus()
  })

  return { onKeydown }
}
