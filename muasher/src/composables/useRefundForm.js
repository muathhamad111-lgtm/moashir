import { computed, ref } from 'vue'
import { useForm } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { financeService } from '@/services/financeService'
import { REFUSED_FILE_CODES, UPLOAD_STATES } from '@/constants/finance'
import { logger } from '@/utils/logger'
import { errorCodeOf, financeErrorMessage, firstFieldErrors } from '@/utils/payments/financeErrors'
import { refundFormData, refundSchema, riyadhDate } from '@/utils/finance/refundSchema'

export function useRefundForm(transaction, { onRecorded }) {
  const i18n = useI18n()
  const toast = useToast()
  const uploadState = ref(UPLOAD_STATES.IDLE)
  const refusal = ref('')
  const submitting = ref(false)

  const total = computed(() => transaction.value.pricing.total)
  const paidOn = computed(() => (transaction.value.paidAt ? riyadhDate(transaction.value.paidAt) : null))
  const form = useForm({
    validationSchema: computed(() => refundSchema(i18n.t, { total: total.value, paidOn: paidOn.value })),
    initialValues: { amount: total.value, refundedOn: riyadhDate(), reason: '', transferReference: '', evidence: null },
  })

  function selectEvidence(file) {
    form.setFieldValue('evidence', file ?? null)
    uploadState.value = UPLOAD_STATES.IDLE
    refusal.value = ''
  }

  function failed(error) {
    const code = errorCodeOf(error)
    form.setErrors(firstFieldErrors(error))
    if (REFUSED_FILE_CODES.includes(code)) {
      uploadState.value = UPLOAD_STATES.REFUSED
      refusal.value = financeErrorMessage(i18n, error, 'finance.refund.failed')
      return
    }
    uploadState.value = UPLOAD_STATES.IDLE
    toast.error(financeErrorMessage(i18n, error, 'finance.refund.failed'))
  }

  const submit = form.handleSubmit(async (values) => {
    if (submitting.value) return
    submitting.value = true
    uploadState.value = UPLOAD_STATES.PENDING
    refusal.value = ''
    logger.breadcrumb('form', 'finance.recordRefund', { fields: Object.keys(values) })
    try {
      const { data } = await financeService.recordRefund(transaction.value.id, refundFormData(values))
      uploadState.value = UPLOAD_STATES.CLEAN
      toast.success(i18n.t('finance.refund.recorded'))
      onRecorded(data)
    } catch (error) {
      failed(error)
    } finally {
      submitting.value = false
    }
  })

  return { form, total, paidOn, uploadState, refusal, submitting, selectEvidence, submit }
}
