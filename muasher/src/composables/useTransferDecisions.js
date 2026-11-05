import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { bankTransfersService } from '@/services/bankTransfersService'
import { BANK_TRANSFER_CODES } from '@/constants/bankTransfer'
import { errorCodeOf } from '@/utils/payments/financeErrors'

const CONFLICT_CODES = [BANK_TRANSFER_CODES.ALREADY_DECIDED, BANK_TRANSFER_CODES.ENTITY_DELETED]

function toTarget(item) {
  return {
    id: item.id ?? item.paymentId,
    reference: item.reference,
    entityName: item.entity?.name ?? item.entityName ?? null,
    total: item.pricing?.total ?? item.total ?? null,
    currency: item.pricing?.currency ?? item.currency ?? 'SAR',
  }
}

export function useTransferDecisions({ onDecided = () => {} } = {}) {
  const { t } = useI18n()
  const toast = useToast()
  const auth = useAuthStore()
  const approveTargets = ref([])
  const rejectTargets = ref([])
  const approveOpen = ref(false)
  const rejectOpen = ref(false)
  const conflict = ref(null)
  const outcome = ref(null)

  const asksReason = computed(() => auth.isSuperAdmin)

  function openApprove(items) {
    approveTargets.value = items.map(toTarget)
    approveOpen.value = true
  }

  function openReject(items) {
    rejectTargets.value = items.map(toTarget)
    rejectOpen.value = true
  }

  function settledSingle(error) {
    const code = errorCodeOf(error)
    if (!CONFLICT_CODES.includes(code)) throw error
    conflict.value = code
    onDecided({ conflict: code })
  }

  async function runSingle(request, successKey) {
    try {
      const { data } = await request()
      conflict.value = null
      toast.success(t(successKey, { number: data.receipt?.number ?? '' }))
      onDecided({ transfer: data })
    } catch (error) {
      settledSingle(error)
    }
  }

  async function runBulk(request) {
    const { data } = await request()
    outcome.value = data
    onDecided({ outcome: data })
  }

  function approve(reason = null) {
    const ids = approveTargets.value.map((target) => target.id)
    if (ids.length === 1)
      return runSingle(
        () => bankTransfersService.approve(ids[0], reason),
        'finance.transfers.toasts.approved',
      )
    return runBulk(() => bankTransfersService.bulkApprove(ids, reason))
  }

  function reject({ rejectionReason, note, reason = null }) {
    const ids = rejectTargets.value.map((target) => target.id)
    const rejection = { rejectionReason, note }
    if (ids.length === 1)
      return runSingle(
        () => bankTransfersService.reject(ids[0], rejection, reason),
        'finance.transfers.toasts.rejected',
      )
    return runBulk(() => bankTransfersService.bulkReject(ids, rejection, reason))
  }

  function clearOutcome() {
    outcome.value = null
  }

  return {
    approveTargets,
    rejectTargets,
    approveOpen,
    rejectOpen,
    conflict,
    outcome,
    asksReason,
    openApprove,
    openReject,
    approve,
    reject,
    clearOutcome,
  }
}
