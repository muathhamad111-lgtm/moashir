import { ref } from 'vue'
import { paymentsService } from '@/services/paymentsService'
import { logger } from '@/utils/logger'
import { moyasarConfigOf } from '@/utils/payments/moyasarConfig'
import { loadMoyasarForm, moyasarAssetsOf } from '@/utils/payments/moyasarLoader'

export const FORM_STATES = { IDLE: 'idle', LOADING: 'loading', READY: 'ready', ERROR: 'error' }

export function useMoyasarForm({ onVerified, onVerifyFailed }) {
  const state = ref(FORM_STATES.IDLE)
  const verifying = ref(false)

  function completedHandler(paymentId) {
    return async (providerPayment) => {
      verifying.value = true
      try {
        const envelope = await paymentsService.verify(paymentId, providerPayment.id)
        onVerified(envelope.data, envelope.meta?.verification)
      } catch (error) {
        onVerifyFailed(error)
        throw error
      } finally {
        verifying.value = false
      }
    }
  }

  async function mount(element, { payment, checkout }) {
    state.value = FORM_STATES.LOADING
    logger.breadcrumb('payments', 'checkout.mountForm', {
      mode: checkout.mode,
      pattern: checkout.pattern,
    })
    try {
      const moyasar = await loadMoyasarForm(moyasarAssetsOf(checkout))
      moyasar.init(
        moyasarConfigOf(checkout, { element, onCompleted: completedHandler(payment.id) }),
      )
      state.value = FORM_STATES.READY
    } catch (error) {
      logger.error(error, { action: 'payments.checkout.mountForm' })
      state.value = FORM_STATES.ERROR
    }
  }

  return { state, verifying, mount }
}
