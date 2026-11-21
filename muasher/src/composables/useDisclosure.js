import { onBeforeUnmount, ref, watch } from 'vue'

export function useDisclosure({ rootRef, triggerRef }) {
  const isOpen = ref(false)

  function open() {
    isOpen.value = true
  }

  function close() {
    isOpen.value = false
  }

  function toggle() {
    isOpen.value = !isOpen.value
  }

  function closeToTrigger() {
    close()
    triggerRef.value?.focus()
  }

  function onKeydown(event) {
    if (event.key !== 'Escape' || !isOpen.value) return
    event.preventDefault()
    event.stopPropagation()
    closeToTrigger()
  }

  function onFocusOut(event) {
    if (event.relatedTarget && !rootRef.value?.contains(event.relatedTarget)) close()
  }

  function onPointerDown(event) {
    if (!rootRef.value?.contains(event.target)) close()
  }

  watch(isOpen, (openNow) => {
    const method = openNow ? 'addEventListener' : 'removeEventListener'
    document[method]('pointerdown', onPointerDown)
  })

  onBeforeUnmount(() => document.removeEventListener('pointerdown', onPointerDown))

  return { isOpen, open, close, toggle, onKeydown, onFocusOut }
}
