import { computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTable } from '@/composables/useTable'
import { useFilters } from '@/composables/useFilters'
import { usePagination } from '@/composables/usePagination'
import { useToast } from '@/composables/useToast'
import { privacyRequestsService } from '@/services/privacyRequestsService'
import { statusLabelKey } from '@/utils/canonicalStatus'
import { PRIVACY_REQUEST_KINDS, PRIVACY_REQUEST_STATUSES } from '@/constants/entityEnums'

export const PRIVACY_DECISIONS = { FULFIL: 'fulfil', REJECT: 'reject' }

export function usePrivacyRequests() {
  const { t } = useI18n()
  const toast = useToast()
  const filters = useFilters({ status: PRIVACY_REQUEST_STATUSES.OPEN, kind: '' })
  const paginator = usePagination({ perPage: 10 })
  const table = useTable({
    fetcher: (params) => privacyRequestsService.list(params),
    filters,
    pagination: paginator,
  })
  const decision = reactive({ open: false, kind: null, request: null })

  const statusOptions = computed(() => [
    { value: '', label: t('entities.filters.allStatuses') },
    ...Object.values(PRIVACY_REQUEST_STATUSES).map((value) => ({
      value,
      label: t(statusLabelKey(value)),
    })),
  ])
  const kindOptions = computed(() => [
    { value: '', label: t('entities.privacy.allKinds') },
    ...PRIVACY_REQUEST_KINDS.map((value) => ({
      value,
      label: t(`entities.enums.privacyKind.${value}`),
    })),
  ])

  function startDecision(kind, request) {
    decision.kind = kind
    decision.request = request
    decision.open = true
  }

  function decide(reason) {
    const { id } = decision.request
    return decision.kind === PRIVACY_DECISIONS.FULFIL
      ? privacyRequestsService.fulfil(id, reason || null)
      : privacyRequestsService.reject(id, reason)
  }

  async function afterDecision() {
    toast.success(t(`entities.privacy.toasts.${decision.kind}`))
    await table.load()
  }

  return {
    ...table,
    filters,
    paginator,
    statusOptions,
    kindOptions,
    decision,
    startDecision,
    decide,
    afterDecision,
  }
}
