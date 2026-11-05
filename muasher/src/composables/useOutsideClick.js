import { onBeforeUnmount, onMounted } from 'vue'

export function useOutsideClick(rootRef, { isActive, onOutside }) {
  function onDocumentClick(event) {
    if (!isActive.value) return
    if (rootRef.value && !rootRef.value.contains(event.target)) onOutside()
  }

  onMounted(() => document.addEventListener('click', onDocumentClick))
  onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick))
}
