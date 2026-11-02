import { reactive, readonly } from 'vue'

const DEFAULT_DURATION = 5000
const state = reactive({ items: [] })
let seed = 0

function dismiss(id) {
  const index = state.items.findIndex((item) => item.id === id)
  if (index !== -1) state.items.splice(index, 1)
}

function push(type, message, duration = DEFAULT_DURATION) {
  seed += 1
  const id = seed
  state.items.push({ id, type, message })
  if (duration > 0) setTimeout(() => dismiss(id), duration)
  return id
}

export const toast = {
  success: (message, duration) => push('success', message, duration),
  error: (message, duration) => push('error', message, duration),
  info: (message, duration) => push('info', message, duration),
  dismiss,
}

export function useToast() {
  return { toasts: readonly(state).items, ...toast }
}
