import { computed, ref } from 'vue'
import { bankTransfersService } from '@/services/bankTransfersService'
import { usePermissions } from '@/composables/usePermissions'
import { useTransferDecisions } from '@/composables/useTransferDecisions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { PAYMENT_STATUSES } from '@/constants/payments'
import { errorStatusOf } from '@/utils/payments/financeErrors'

const NOT_FOUND = 404

export function useBankTransferReview(paymentId) {
  const { can } = usePermissions()
  const status = ref('loading')
  const transfer = ref(null)
  const errorMessage = ref('')

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      transfer.value = (await bankTransfersService.get(paymentId)).data
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = errorStatusOf(error) === NOT_FOUND ? 'not-found' : 'error'
    }
  }

  const decisions = useTransferDecisions({ onDecided: () => load() })

  const isReviewer = computed(() => can(PERMISSIONS.FINANCE_TRANSFERS_REVIEW))
  const isUnderReview = computed(() => transfer.value?.status === PAYMENT_STATUSES.UNDER_REVIEW)
  const isFrozen = computed(() => transfer.value?.entity.isFrozen === true)
  const canDecide = computed(() => isReviewer.value && isUnderReview.value && !isFrozen.value)
  const isDecided = computed(() => Boolean(transfer.value?.reviewedAt))

  function requestProofLink() {
    return bankTransfersService.requestProofDownload(paymentId)
  }

  return {
    status,
    transfer,
    errorMessage,
    decisions,
    isReviewer,
    isUnderReview,
    isFrozen,
    canDecide,
    isDecided,
    load,
    requestProofLink,
  }
}
