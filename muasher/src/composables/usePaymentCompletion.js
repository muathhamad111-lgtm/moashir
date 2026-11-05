import { computed, onMounted, ref, unref } from 'vue'
import { paymentsService } from '@/services/paymentsService'
import { CHECKOUT_PHASES, useCheckout } from '@/composables/useCheckout'
import { canCompletePayment } from '@/utils/payments/myPaymentRow'

const NOT_FOUND = 404

export const COMPLETION_STATES = Object.freeze({
  LOADING: 'loading',
  CHECKOUT: 'checkout',
  CLOSED: 'closed',
  MISSING: 'missing',
  ERROR: 'error',
})

export function usePaymentCompletion(paymentId, { onReplaced = () => {} } = {}) {
  const order = ref(null)
  const state = ref(COMPLETION_STATES.LOADING)
  const errorMessage = ref('')
  const purpose = computed(() => order.value?.purpose ?? '')
  const checkout = useCheckout(purpose)

  const payment = computed(() => checkout.payment.value ?? order.value)
  const isReady = computed(() => checkout.phase.value === CHECKOUT_PHASES.READY)

  async function startCheckout() {
    await checkout.start()
    const opened = checkout.payment.value
    if (opened && opened.id !== unref(paymentId)) onReplaced(opened.id)
  }

  async function load() {
    state.value = COMPLETION_STATES.LOADING
    errorMessage.value = ''
    try {
      const { data } = await paymentsService.get(unref(paymentId))
      order.value = data
      if (!canCompletePayment(data)) {
        state.value = COMPLETION_STATES.CLOSED
        return
      }
      state.value = COMPLETION_STATES.CHECKOUT
      await startCheckout()
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      state.value =
        error?.normalized?.status === NOT_FOUND
          ? COMPLETION_STATES.MISSING
          : COMPLETION_STATES.ERROR
    }
  }

  onMounted(load)

  return { order, payment, purpose, state, errorMessage, isReady, checkout, load, startCheckout }
}
