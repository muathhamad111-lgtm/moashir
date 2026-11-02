import { ref } from 'vue'
import { paymentsService } from '@/services/paymentsService'
import { errorStatusOf } from '@/utils/payments/financeErrors'

const NOT_FOUND = 404

export function usePaymentDetail(paymentId) {
  const status = ref('loading')
  const payment = ref(null)
  const receipt = ref(null)
  const errorMessage = ref('')

  async function loadReceipt() {
    receipt.value = payment.value.receipt ? (await paymentsService.getReceipt(paymentId)).data : null
  }

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      payment.value = (await paymentsService.get(paymentId)).data
      await loadReceipt()
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = errorStatusOf(error) === NOT_FOUND ? 'not-found' : 'error'
    }
  }

  function requestReceiptLink() {
    return paymentsService.requestReceiptDownload(paymentId)
  }

  return { status, payment, receipt, errorMessage, load, requestReceiptLink }
}
