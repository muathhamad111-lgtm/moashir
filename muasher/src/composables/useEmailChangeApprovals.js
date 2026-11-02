import { computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTable } from '@/composables/useTable'
import { useFilters } from '@/composables/useFilters'
import { usePagination } from '@/composables/usePagination'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { entityEmailChangesService } from '@/services/entityEmailChangesService'
import { localizedText } from '@/utils/localizedText'
import { statusLabelKey } from '@/utils/canonicalStatus'
import { EMAIL_CHANGE_STATUSES as S } from '@/constants/entityEnums'

const FILTER_STATUSES = [S.AWAITING_APPROVAL, S.APPLIED, S.REJECTED, S.CANCELLED, S.EXPIRED]

export function useEmailChangeApprovals() {
  const { t, locale } = useI18n()
  const toast = useToast()
  const { confirm } = useConfirm()
  const filters = useFilters({ status: S.AWAITING_APPROVAL })
  const paginator = usePagination({ perPage: 10 })
  const table = useTable({ fetcher: (params) => entityEmailChangesService.list(params), filters, pagination: paginator })
  const rejection = reactive({ open: false, change: null })

  const statusOptions = computed(() => [
    ...FILTER_STATUSES.map((value) => ({ value, label: t(statusLabelKey(value)) })),
    { value: 'all', label: t('entities.emailChanges.allStatuses') },
  ])

  function entityName(change) {
    return localizedText(change.entity?.name, locale.value)
  }

  async function approve(change) {
    const accepted = await confirm({
      title: t('entities.emailChanges.approveTitle'),
      message: t('entities.emailChanges.approveMessage', { name: entityName(change), email: change.newEmail }),
      confirmLabel: t('entities.emailChanges.approve'),
    })
    if (!accepted) return
    try {
      await entityEmailChangesService.approve(change.id)
      toast.success(t('entities.emailChanges.approved'))
      await table.load()
    } catch (error) {
      toast.error(error?.normalized?.message || t('errors.unexpected'))
    }
  }

  function startReject(change) {
    rejection.change = change
    rejection.open = true
  }

  function reject(reason) {
    return entityEmailChangesService.reject(rejection.change.id, reason)
  }

  async function afterReject() {
    toast.success(t('entities.emailChanges.rejected'))
    await table.load()
  }

  return { ...table, filters, paginator, statusOptions, rejection, entityName, approve, startReject, reject, afterReject }
}
