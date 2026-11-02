import { PROVIDER_PAYMENT_ID_PATTERN } from '@/constants/payments'

export function providerPaymentIdOf(query) {
  const value = Array.isArray(query?.id) ? query.id[0] : query?.id
  return typeof value === 'string' && PROVIDER_PAYMENT_ID_PATTERN.test(value) ? value : null
}

export function secondsUntil(iso, now = Date.now()) {
  if (!iso) return 0
  return Math.max(0, Math.floor((new Date(iso).getTime() - now) / 1000))
}

export function countdownLabel(seconds) {
  const minutes = String(Math.floor(seconds / 60)).padStart(2, '0')
  const rest = String(seconds % 60).padStart(2, '0')
  return `${minutes}:${rest}`
}
