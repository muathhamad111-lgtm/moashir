import { ref } from 'vue'
import { useForm } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { financeService } from '@/services/financeService'
import { logger } from '@/utils/logger'
import { financeErrorMessage, firstFieldErrors } from '@/utils/payments/financeErrors'
import { manualSettlePayload, manualSettleSchema } from '@/utils/finance/manualSettleSchema'

export function useManualSettle(paymentId, { onSettled }) {
  const i18n = useI18n()
  const toast = useToast()
  const submitting = ref(false)
  const form = useForm({
    validationSchema: manualSettleSchema(i18n.t),
    initialValues: { status: 'paid', providerPaymentId: '', reason: '' },
  })

  const submit = form.handleSubmit(async (values) => {
    if (submitting.value) return
    submitting.value = true
    logger.breadcrumb('form', 'finance.manualSettle', { fields: Object.keys(values) })
    try {
      const { data } = await financeService.manualSettle(paymentId, manualSettlePayload(values))
      toast.success(i18n.t('finance.settle.done'))
      onSettled(data)
    } catch (error) {
      form.setErrors(firstFieldErrors(error))
      toast.error(financeErrorMessage(i18n, error, 'finance.settle.failed'))
    } finally {
      submitting.value = false
    }
  })

  function reset() {
    form.resetForm({ values: { status: 'paid', providerPaymentId: '', reason: '' } })
  }

  return { form, submitting, submit, reset }
}
