import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { paymentsService } from '@/services/paymentsService'
import { financeErrorMessage } from '@/utils/payments/financeErrors'

export function useCardOrderCancel({ onCancelled = () => {} } = {}) {
  const i18n = useI18n()
  const toast = useToast()
  const open = ref(false)

  async function cancel(payment) {
    try {
      const { data } = await paymentsService.cancel(payment.id)
      open.value = false
      onCancelled(data)
      return true
    } catch (error) {
      toast.error(financeErrorMessage(i18n, error, 'portal.checkoutMethod.change.failed'))
      return false
    }
  }

  return { open, cancel }
}
