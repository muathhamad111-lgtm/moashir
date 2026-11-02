import { onBeforeUnmount, onMounted, ref } from 'vue'

export function useDisclosure() {
  const isOpen = ref(false)
  const rootRef = ref(null)

  function open() {
    isOpen.value = true
  }

  function close() {
    isOpen.value = false
  }

  function toggle() {
    isOpen.value = !isOpen.value
  }

  function onDocumentClick(event) {
    if (!isOpen.value) return
    if (rootRef.value && !rootRef.value.contains(event.target)) close()
  }

  function onDocumentKeydown(event) {
    if (event.key === 'Escape' && isOpen.value) close()
  }

  onMounted(() => {
    document.addEventListener('click', onDocumentClick)
    document.addEventListener('keydown', onDocumentKeydown)
  })

  onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick)
    document.removeEventListener('keydown', onDocumentKeydown)
  })

  return { isOpen, rootRef, open, close, toggle }
}
