import { computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTable } from '@/composables/useTable'
import { useFilters } from '@/composables/useFilters'
import { usePagination } from '@/composables/usePagination'
import { useToast } from '@/composables/useToast'
import { identityService } from '@/services/identityService'
import { useIdentityChangeDetail } from '@/composables/useIdentityChangeDetail'
import { statusLabelKey } from '@/utils/canonicalStatus'
import {
  IDENTITY_CHANGE_STATUSES as S,
  IDENTITY_CHANGE_STATUS_VALUES,
} from '@/constants/identityChanges'

const DECISIONS = Object.freeze({ APPROVE: 'approve', REJECT: 'reject' })
const DECISION_TOASTS = Object.freeze({
  [DECISIONS.APPROVE]: 'nationalIdentity.review.approved',
  [DECISIONS.REJECT]: 'nationalIdentity.review.rejected',
})

export function useIdentityChangeReviews() {
  const { t } = useI18n()
  const toast = useToast()
  const filters = useFilters({ status: S.PENDING }, { defaults: { status: S.PENDING } })
  const paginator = usePagination({ perPage: 10 })
  const table = useTable({
    fetcher: (params) => identityService.reviewQueue(params),
    filters,
    pagination: paginator,
  })
  const decision = reactive({ open: false, kind: DECISIONS.APPROVE, change: null })
  const { detail, view } = useIdentityChangeDetail()

  const statusOptions = computed(() => [
    ...IDENTITY_CHANGE_STATUS_VALUES.map((value) => ({ value, label: t(statusLabelKey(value)) })),
    { value: 'all', label: t('dataList.filter.all') },
  ])

  function startDecision(kind, change) {
    detail.open = false
    decision.kind = kind
    decision.change = change
    decision.open = true
  }

  function decide(note) {
    const id = decision.change.id
    return decision.kind === DECISIONS.APPROVE
      ? identityService.approve(id, note)
      : identityService.reject(id, note)
  }

  async function afterDecision() {
    toast.success(t(DECISION_TOASTS[decision.kind]))
    await table.load()
  }

  return {
    ...table,
    filters,
    paginator,
    statusOptions,
    decision,
    detail,
    DECISIONS,
    view,
    startDecision,
    decide,
    afterDecision,
  }
}
