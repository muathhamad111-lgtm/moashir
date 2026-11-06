import { nextTick } from 'vue'
import { useActiveIndex } from '@/composables/useActiveIndex'
import { isTypeaheadKey, useTypeahead } from '@/composables/useTypeahead'

const MENU_ITEM = '[role="menuitem"]'

export function useMenuNavigation({ items, menuRef, onEscape }) {
  const active = useActiveIndex(() => items().length)
  const typeahead = useTypeahead(() => items().map((item) => item.label))

  async function focusActive() {
    await nextTick()
    const elements = menuRef.value?.querySelectorAll(MENU_ITEM) ?? []
    elements[active.activeIndex.value]?.focus()
  }

  function focusItem(index) {
    active.set(index)
    focusActive()
  }

  function hover(index) {
    if (index !== active.activeIndex.value) focusItem(index)
  }

  function focusFirst() {
    focusItem(0)
  }

  function focusLast() {
    focusItem(items().length - 1)
  }

  function reset() {
    active.clear()
    typeahead.reset()
  }

  const KEYS = {
    ArrowDown: () => active.wrap(1),
    ArrowUp: () => active.wrap(-1),
    Home: () => active.first(),
    End: () => active.last(),
  }

  function onKeydown(event) {
    if (event.key === 'Escape') {
      event.preventDefault()
      event.stopPropagation()
      onEscape()
      return
    }
    const handler = KEYS[event.key]
    if (handler) {
      event.preventDefault()
      handler()
      focusActive()
      return
    }
    if (!isTypeaheadKey(event) || event.key === ' ') return
    const index = typeahead.type(event.key, active.activeIndex.value)
    if (index >= 0) focusItem(index)
  }

  return { activeIndex: active.activeIndex, hover, focusFirst, focusLast, reset, onKeydown }
}
