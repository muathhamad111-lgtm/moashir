import { reactive, readonly } from 'vue'
import { decisionVariantOf } from '@/utils/decisionVariant'

const state = reactive({
  isOpen: false,
  title: '',
  message: '',
  confirmLabel: '',
  cancelLabel: '',
  variant: decisionVariantOf(),
  icon: '',
})

let resolver = null

function confirm({
  title = '',
  message = '',
  confirmLabel = '',
  cancelLabel = '',
  variant = '',
  icon = '',
}) {
  state.title = title
  state.message = message
  state.confirmLabel = confirmLabel
  state.cancelLabel = cancelLabel
  state.variant = decisionVariantOf(variant)
  state.icon = icon
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
