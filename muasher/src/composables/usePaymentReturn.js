import { onBeforeUnmount, ref } from 'vue'
import { paymentsService } from '@/services/paymentsService'
import { PAYMENT_STATUSES, VERIFICATION_RESULTS, VERIFY_POLL_DELAYS_MS } from '@/constants/payments'
import { errorStatusOf } from '@/utils/payments/financeErrors'

export const RETURN_STATES = {
  VERIFYING: 'verifying',
  PAID: 'paid',
  FAILED: 'failed',
  PENDING: 'pending',
  NOTIFY: 'notify',
  CANCELLED: 'cancelled',
  LATE: 'late',
  REFUNDED: 'refunded',
  UNCONFIRMED: 'unconfirmed',
  ERROR: 'error',
  NOT_FOUND: 'not_found',
}

const NOT_FOUND = 404

const OUTCOMES = {
  [PAYMENT_STATUSES.PAID]: RETURN_STATES.PAID,
  [PAYMENT_STATUSES.FAILED]: RETURN_STATES.FAILED,
  [PAYMENT_STATUSES.CANCELLED]: RETURN_STATES.CANCELLED,
  [PAYMENT_STATUSES.REFUNDED]: RETURN_STATES.REFUNDED,
}

function outcomeOf(payment, verification) {
  if (verification === VERIFICATION_RESULTS.LATE_PAYMENT) return RETURN_STATES.LATE
  return OUTCOMES[payment.status] ?? RETURN_STATES.PENDING
}

export function usePaymentReturn(paymentId, providerPaymentId, { delays = VERIFY_POLL_DELAYS_MS } = {}) {
  const state = ref(RETURN_STATES.VERIFYING)
  const payment = ref(null)
  const failure = ref(null)
  const attempt = ref(0)
  let timer = null
  let stopped = false

  function schedule() {
    if (stopped) return
    if (attempt.value >= delays.length) {
      state.value = RETURN_STATES.NOTIFY
      return
    }
    timer = setTimeout(verify, delays[attempt.value])
    attempt.value += 1
  }

  function settled(envelope) {
    payment.value = envelope.data
    state.value = outcomeOf(envelope.data, envelope.meta?.verification)
    if (state.value === RETURN_STATES.PENDING) schedule()
  }

  function failed(error) {
    failure.value = error
    state.value = errorStatusOf(error) === NOT_FOUND ? RETURN_STATES.NOT_FOUND : RETURN_STATES.ERROR
  }

  async function verify() {
    try {
      settled(await paymentsService.verify(paymentId, providerPaymentId))
    } catch (error) {
      failed(error)
    }
  }

  async function readOnly() {
    try {
      const envelope = await paymentsService.get(paymentId)
      payment.value = envelope.data
      const outcome = outcomeOf(envelope.data)
      state.value = outcome === RETURN_STATES.PENDING ? RETURN_STATES.UNCONFIRMED : outcome
    } catch (error) {
      failed(error)
    }
  }

  function start() {
    clearTimeout(timer)
    stopped = false
    attempt.value = 0
    failure.value = null
    state.value = RETURN_STATES.VERIFYING
    return providerPaymentId ? verify() : readOnly()
  }

  onBeforeUnmount(() => {
    stopped = true
    clearTimeout(timer)
  })

  return { state, payment, failure, attempt, start }
}
