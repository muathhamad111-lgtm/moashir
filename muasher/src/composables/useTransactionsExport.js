import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useToast } from '@/composables/useToast'
import { financeService } from '@/services/financeService'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { downloadBlob } from '@/utils/download'
import { financeErrorMessage } from '@/utils/payments/financeErrors'

export function useTransactionsExport() {
  const i18n = useI18n()
  const router = useRouter()
  const toast = useToast()
  const exporting = ref(false)

  function delivered(result) {
    if (result.kind === 'queued') {
      toast.info(i18n.t('finance.export.queued', { count: result.export.rowCount }))
      return router.push({ name: ROUTE_NAMES.FINANCE_EXPORT, params: { id: result.export.id } })
    }
    downloadBlob(result.blob, result.fileName)
    toast.success(i18n.t('finance.export.downloaded', { count: result.rowCount }))
    return null
  }

  async function run(params) {
    if (exporting.value) return
    exporting.value = true
    try {
      await delivered(await financeService.exportTransactions(params))
    } catch (error) {
      toast.error(financeErrorMessage(i18n, error, 'finance.export.failed'))
    } finally {
      exporting.value = false
    }
  }

  return { exporting, run }
}
