import { computed, onMounted, ref, unref } from 'vue'
import { paymentsService } from '@/services/paymentsService'
import { CHECKOUT_PHASES, useCheckout } from '@/composables/useCheckout'
import { canCompletePayment, isBankTransfer } from '@/utils/payments/myPaymentRow'
import { PAYMENT_CHANNELS, PAYMENT_STATUSES } from '@/constants/payments'

const NOT_FOUND = 404

export const COMPLETION_STATES = Object.freeze({
  LOADING: 'loading',
  CHECKOUT: 'checkout',
  TRANSFER: 'transfer',
  CLOSED: 'closed',
  MISSING: 'missing',
  ERROR: 'error',
})

export function usePaymentCompletion(paymentId, { onReplaced = () => {} } = {}) {
  const order = ref(null)
  const state = ref(COMPLETION_STATES.LOADING)
  const errorMessage = ref('')
  const purpose = computed(() => order.value?.purpose ?? '')
  const checkout = useCheckout(
    computed(() => ({ purpose: purpose.value, awardCycleId: order.value?.awardCycle?.id ?? null })),
  )

  const channels = ref([])

  const payment = computed(() => checkout.payment.value ?? order.value)
  const isReady = computed(() => checkout.phase.value === CHECKOUT_PHASES.READY)
  const canSwitchToTransfer = computed(
    () =>
      channels.value.includes(PAYMENT_CHANNELS.BANK_TRANSFER) &&
      payment.value?.status === PAYMENT_STATUSES.CREATED &&
      !isBankTransfer(payment.value),
  )

  async function loadChannels() {
    try {
      channels.value = (await paymentsService.checkoutOptions()).data.channels
    } catch {
      channels.value = []
    }
  }

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
      if (isBankTransfer(data)) {
        state.value = COMPLETION_STATES.TRANSFER
        return
      }
      if (!canCompletePayment(data)) {
        state.value = COMPLETION_STATES.CLOSED
        return
      }
      state.value = COMPLETION_STATES.CHECKOUT
      await Promise.all([startCheckout(), loadChannels()])
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      state.value =
        error?.normalized?.status === NOT_FOUND
          ? COMPLETION_STATES.MISSING
          : COMPLETION_STATES.ERROR
    }
  }

  onMounted(load)

  return {
    order,
    payment,
    purpose,
    state,
    errorMessage,
    isReady,
    checkout,
    canSwitchToTransfer,
    load,
    startCheckout,
  }
}
