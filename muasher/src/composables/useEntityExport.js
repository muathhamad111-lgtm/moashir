import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { entitiesService } from '@/services/entitiesService'
import { downloadBlob } from '@/utils/download'

export function useEntityExport() {
  const { t } = useI18n()
  const toast = useToast()
  const exporting = ref(false)

  async function exportCsv(params) {
    if (exporting.value) return
    exporting.value = true
    try {
      const { blob, fileName, rowCount } = await entitiesService.exportCsv(params)
      downloadBlob(blob, fileName)
      toast.success(t('entities.toasts.exported', { count: rowCount }))
    } catch (error) {
      toast.error(error?.normalized?.message || t('entities.toasts.exportFailed'))
    } finally {
      exporting.value = false
    }
  }

  return { exporting, exportCsv }
}
