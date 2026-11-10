import { DECISION_VARIANTS } from '@/constants/modal'

export function decisionVariantOf(value) {
  return Object.values(DECISION_VARIANTS).includes(value) ? value : DECISION_VARIANTS.CONFIRM
}

export function buttonVariantOf(variant) {
  return variant === DECISION_VARIANTS.DANGER ? 'danger' : 'primary'
}

export function focusesSafeAction(variant) {
  return variant !== DECISION_VARIANTS.CONFIRM
}
