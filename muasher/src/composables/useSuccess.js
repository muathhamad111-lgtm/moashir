import { reactive, readonly } from 'vue'

const state = reactive({ isOpen: false, title: '', message: '', sequence: 0 })

let resolver = null

function settle() {
  const resolve = resolver
  resolver = null
  resolve?.()
}

function showSuccess({ title = '', message = '' } = {}) {
  settle()
  state.title = title
  state.message = message
  state.sequence += 1
  state.isOpen = true
  return new Promise((resolve) => {
    resolver = resolve
  })
}

function closeSuccess() {
  state.isOpen = false
  settle()
}

export const success = { show: showSuccess, close: closeSuccess }

export function useSuccess() {
  return { successState: readonly(state), showSuccess, closeSuccess }
}
