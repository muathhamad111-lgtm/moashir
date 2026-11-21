import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { usersService } from '@/services/usersService'
import { downloadBlob } from '@/utils/download'

export function useUsersExport() {
  const { t } = useI18n()
  const toast = useToast()
  const exporting = ref(false)

  async function exportCsv(params) {
    if (exporting.value) return
    exporting.value = true
    try {
      const { blob, fileName, rowCount } = await usersService.exportCsv(params)
      downloadBlob(blob, fileName)
      toast.success(t('users.toasts.exported', { count: rowCount }))
    } catch (error) {
      toast.error(error?.normalized?.message || t('users.toasts.exportFailed'))
    } finally {
      exporting.value = false
    }
  }

  return { exporting, exportCsv }
}
