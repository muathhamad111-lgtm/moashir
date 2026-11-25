import { onBeforeUnmount, onMounted } from 'vue'

const TYPING = 'input, textarea, select, [contenteditable="true"], [role="combobox"]'

function isTyping(event) {
  return event.target instanceof Element && Boolean(event.target.closest(TYPING))
}

/**
 * While a message is open, Up and Down move through the message list and Escape closes it. With no
 * message open the keys scroll the page as usual; keys pressed while the user types are left alone.
 */
export function useInboxKeyboard({ ids, selectedId, open, close }) {
  function move(step) {
    const list = ids()
    if (list.length === 0) return
    const index = list.indexOf(selectedId())
    const next = index === -1 ? 0 : Math.min(list.length - 1, Math.max(0, index + step))
    if (list[next] !== selectedId()) open(list[next])
  }

  const STEPS = { ArrowDown: 1, ArrowUp: -1 }

  function modified(event) {
    return event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey
  }

  function closeOnEscape() {
    if (!selectedId() || document.querySelector('[role="dialog"], [role="alertdialog"]')) return
    close()
  }

  function onKey(event) {
    if (modified(event)) return
    if (event.key === 'Escape') {
      closeOnEscape()
      return
    }
    if (!STEPS[event.key] || !selectedId() || isTyping(event)) return
    event.preventDefault()
    move(STEPS[event.key])
  }

  onMounted(() => window.addEventListener('keydown', onKey))
  onBeforeUnmount(() => window.removeEventListener('keydown', onKey))

  return { move }
}
