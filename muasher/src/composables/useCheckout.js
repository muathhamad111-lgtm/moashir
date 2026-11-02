import { computed, ref, watch } from 'vue'
import { useCountdown } from '@/composables/useCountdown'
import { useIdempotencyKey } from '@/composables/useIdempotencyKey'
import { paymentsService } from '@/services/paymentsService'
import { PAYMENT_STATUSES } from '@/constants/payments'
import { errorCodeOf } from '@/utils/payments/financeErrors'

export const CHECKOUT_PHASES = {
  IDLE: 'idle',
  STARTING: 'starting',
  READY: 'ready',
  IN_PROGRESS: 'in-progress',
  EXPIRED: 'expired',
  BLOCKED: 'blocked',
}

function phaseOf(payment, checkout) {
  if (checkout) return CHECKOUT_PHASES.READY
  if (payment.status === PAYMENT_STATUSES.PENDING) return CHECKOUT_PHASES.IN_PROGRESS
  return CHECKOUT_PHASES.EXPIRED
}

export function useCheckout(purpose) {
  const keys = useIdempotencyKey()
  const phase = ref(CHECKOUT_PHASES.IDLE)
  const payment = ref(null)
  const checkout = ref(null)
  const failure = ref(null)
  const existingOrder = ref(false)

  const deadline = computed(() => (phase.value === CHECKOUT_PHASES.READY ? checkout.value?.expiresAt : null))
  const countdown = useCountdown(deadline)
  const isStarting = computed(() => phase.value === CHECKOUT_PHASES.STARTING)
  const isTest = computed(() => checkout.value?.mode === 'test' || payment.value?.isTest === true)

  watch(countdown.expired, (expired) => {
    if (expired && phase.value === CHECKOUT_PHASES.READY) phase.value = CHECKOUT_PHASES.EXPIRED
  })

  function rejected(error) {
    if (error?.response) keys.renew()
    failure.value = { code: errorCodeOf(error), error }
    phase.value = CHECKOUT_PHASES.BLOCKED
  }

  async function start() {
    if (isStarting.value) return
    phase.value = CHECKOUT_PHASES.STARTING
    failure.value = null
    try {
      const envelope = await paymentsService.create(purpose, keys.current())
      keys.renew()
      const { checkout: checkoutObject, ...ownPayment } = envelope.data
      payment.value = ownPayment
      checkout.value = checkoutObject
      existingOrder.value = envelope.meta?.existingOrder === true
      phase.value = phaseOf(ownPayment, checkoutObject)
    } catch (error) {
      rejected(error)
    }
  }

  function applyVerified(verified) {
    payment.value = verified
  }

  return { phase, payment, checkout, failure, existingOrder, countdown, isStarting, isTest, start, applyVerified }
}
