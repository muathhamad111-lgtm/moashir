import { reactive, readonly } from 'vue'

const state = reactive({
  isOpen: false,
  title: '',
  message: '',
  confirmLabel: '',
  cancelLabel: '',
  variant: 'primary',
})

let resolver = null

function confirm({ title = '', message = '', confirmLabel = '', cancelLabel = '', variant = 'primary' }) {
  state.title = title
  state.message = message
  state.confirmLabel = confirmLabel
  state.cancelLabel = cancelLabel
  state.variant = variant
  state.isOpen = true
  return new Promise((resolve) => {
    resolver = resolve
  })
}

function settle(result) {
  state.isOpen = false
  if (resolver) {
    resolver(result)
    resolver = null
  }
}

export function useConfirm() {
  return {
    confirmState: readonly(state),
    confirm,
    accept: () => settle(true),
    dismiss: () => settle(false),
  }
}
