import { ref, watch } from 'vue'

export function useReasonPrompt() {
  const open = ref(false)
  let resolver = null

  function settle(value) {
    const resolve = resolver
    resolver = null
    resolve?.(value)
  }

  function prompt() {
    settle(null)
    open.value = true
    return new Promise((resolve) => {
      resolver = resolve
    })
  }

  function provide(reason) {
    settle(reason)
    return reason
  }

  watch(open, (isOpen) => {
    if (!isOpen) settle(null)
  })

  return { open, prompt, provide }
}
