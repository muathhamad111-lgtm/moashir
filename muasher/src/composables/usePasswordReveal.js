import { ref, watch } from 'vue'

export function usePasswordReveal(value) {
  const revealed = ref(false)

  watch(value, (current) => {
    if (!String(current ?? '').length) revealed.value = false
  })

  function toggle() {
    revealed.value = !revealed.value
  }

  return { revealed, toggle }
}
