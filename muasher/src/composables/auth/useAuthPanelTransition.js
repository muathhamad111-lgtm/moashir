import { watch } from 'vue'
import { slideDirection, swipeIn, swipeOut } from '@/composables/auth/authSlide'
import { injectAuthViewport } from '@/composables/auth/useAuthViewport'

// Swipes one panel of a page out while the next swipes in beside it, ordered by `order`. Focus
// stays where the visitor put it (the tab they chose); the auth card follows the new panel height.
export function useAuthPanelTransition(source, order) {
  const viewport = injectAuthViewport()
  let direction = 0

  watch(
    source,
    (to, from) => {
      direction = slideDirection(order.indexOf(from), order.indexOf(to))
    },
    { flush: 'sync' },
  )

  function onBeforeLeave() {
    if (direction) viewport.settle()
  }

  function onLeave(panel, done) {
    swipeOut(panel, direction, done)
  }

  function onEnter(panel, done) {
    swipeIn(panel, direction, done)
  }

  return { onBeforeLeave, onLeave, onEnter }
}
