import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePagination } from '@/composables/usePagination'
import { useTable } from '@/composables/useTable'
import { useToast } from '@/composables/useToast'
import { auditLogService } from '@/services/auditLogService'
import { AUDIT_FILTER_KEYS, AUDIT_LIMITS } from '@/constants/auditLog'
import { downloadBlob } from '@/utils/download'
import { filterParams } from '@/utils/queryParams'

const FORBIDDEN = 403
const UNPROCESSABLE = 422

function emptyFilters() {
  return Object.fromEntries(AUDIT_FILTER_KEYS.map((key) => [key, '']))
}

function trimmed(values) {
  return Object.fromEntries(
    Object.entries(values).map(([key, value]) => [key, String(value ?? '').trim()]),
  )
}

export function useAuditLog() {
  const { t } = useI18n()
  const toast = useToast()
  const draft = reactive(emptyFilters())
  const applied = reactive(emptyFilters())
  const fieldErrors = ref({})
  const forbidden = ref(false)
  const exporting = ref(false)
  const paginator = usePagination({ perPage: AUDIT_LIMITS.perPage })

  const appliedParams = computed(() => filterParams(applied))
  const filters = { toParams: () => appliedParams.value }

  async function fetcher(params) {
    fieldErrors.value = {}
    try {
      return await auditLogService.list(params)
    } catch (error) {
      forbidden.value = error?.normalized?.status === FORBIDDEN
      if (error?.normalized?.status === UNPROCESSABLE)
        fieldErrors.value = error.normalized.fieldErrors ?? {}
      throw error
    }
  }

  const table = useTable({ fetcher, filters, pagination: paginator })

  function apply() {
    Object.assign(applied, trimmed(draft))
    paginator.setPage(1)
  }

  function reset() {
    Object.assign(draft, emptyFilters())
    apply()
  }

  function filterError(key) {
    return fieldErrors.value?.[`filter.${key}`]?.[0] ?? ''
  }

  async function exportCsv(reason) {
    if (exporting.value) return false
    exporting.value = true
    try {
      const payload = { filter: filterPayload(), ...(reason ? { reason } : {}) }
      const { blob, fileName, rowCount } = await auditLogService.exportCsv(payload)
      downloadBlob(blob, fileName)
      toast.success(t('auditLog.toasts.exported', { count: rowCount }))
      return true
    } catch (error) {
      toast.error(error?.normalized?.message || t('auditLog.toasts.exportFailed'))
      return false
    } finally {
      exporting.value = false
    }
  }

  function filterPayload() {
    return Object.fromEntries(Object.entries(applied).filter(([, value]) => value !== ''))
  }

  const isFiltered = computed(() => Object.values(applied).some(Boolean))

  return {
    draft,
    paginator,
    forbidden,
    exporting,
    isFiltered,
    apply,
    reset,
    filterError,
    exportCsv,
    ...table,
  }
}
