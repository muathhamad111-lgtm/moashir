import { computed, ref } from 'vue'
import { usePermissions } from '@/composables/usePermissions'
import { useAuthStore } from '@/stores/auth'
import { financeService } from '@/services/financeService'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { OPEN_PAYMENT_STATUSES, PAYMENT_STATUSES, RECEIPT_STATUSES } from '@/constants/payments'
import { errorStatusOf } from '@/utils/payments/financeErrors'

const NOT_FOUND = 404

export function useTransactionDetail(paymentId) {
  const { can } = usePermissions()
  const auth = useAuthStore()
  const status = ref('loading')
  const transaction = ref(null)
  const errorMessage = ref('')

  const receipt = computed(() => transaction.value?.receipt ?? null)
  const refund = computed(() => transaction.value?.refund ?? null)
  const canRecordRefund = computed(
    () => can(PERMISSIONS.FINANCE_REFUNDS_MANAGE) && transaction.value?.status === PAYMENT_STATUSES.PAID && !refund.value,
  )
  const canReissue = computed(() => can(PERMISSIONS.FINANCE_RECEIPTS_MANAGE) && receipt.value?.status === RECEIPT_STATUSES.ISSUED)
  const canSettle = computed(() => auth.isSuperAdmin && OPEN_PAYMENT_STATUSES.includes(transaction.value?.status))

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      transaction.value = (await financeService.getTransaction(paymentId)).data
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = errorStatusOf(error) === NOT_FOUND ? 'not-found' : 'error'
    }
  }

  async function reissue(reason) {
    const { data } = await financeService.reissueReceipt(receipt.value.id, reason)
    transaction.value = { ...transaction.value, receipt: data }
    return data
  }

  function replace(detail) {
    transaction.value = detail
  }

  return {
    status,
    transaction,
    receipt,
    refund,
    errorMessage,
    canRecordRefund,
    canReissue,
    canSettle,
    load,
    reissue,
    replace,
    requestReceiptLink: () => financeService.requestReceiptDownload(receipt.value.id),
    requestEvidenceLink: () => financeService.requestEvidenceDownload(refund.value.id),
  }
}
