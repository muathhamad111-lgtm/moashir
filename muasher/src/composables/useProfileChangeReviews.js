import { computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTable } from '@/composables/useTable'
import { useFilters } from '@/composables/useFilters'
import { usePagination } from '@/composables/usePagination'
import { profileChangeRequestsService } from '@/services/profileChangeRequestsService'
import { localizedText } from '@/utils/localizedText'
import { statusLabelKey } from '@/utils/canonicalStatus'
import { changeItemLabelKey } from '@/utils/entityProfile/changeItems'
import { PROFILE_CHANGE_STATUSES as S } from '@/constants/entityEnums'

const FILTER_STATUSES = [S.PENDING, S.APPROVED, S.PARTIALLY_APPROVED, S.REJECTED, S.CANCELLED]

/** The staff queue of entity profile change requests and the request open for review. */
export function useProfileChangeReviews() {
  const { t, locale } = useI18n()
  const filters = useFilters({ status: S.PENDING })
  const paginator = usePagination({ perPage: 10 })
  const table = useTable({
    fetcher: (params) => profileChangeRequestsService.list(params),
    filters,
    pagination: paginator,
  })
  const review = reactive({ open: false, id: null })

  const statusOptions = computed(() => [
    ...FILTER_STATUSES.map((value) => ({ value, label: t(statusLabelKey(value)) })),
    { value: 'all', label: t('dataList.filter.all') },
  ])
  const pendingTotal = computed(() =>
    filters.filters.status === S.PENDING ? paginator.pagination.total : null,
  )

  function entityName(request) {
    return localizedText(request.entity?.name, locale.value)
  }

  function itemLabels(request) {
    return request.items
      .map((item) => t(changeItemLabelKey(item)))
      .join(t('portal.profile.listSeparator'))
  }

  function openReview(request) {
    review.id = request.id
    review.open = true
  }

  return {
    ...table,
    filters,
    paginator,
    statusOptions,
    pendingTotal,
    review,
    entityName,
    itemLabels,
    openReview,
  }
}
