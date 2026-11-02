import { reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { filesService } from '@/services/filesService'
import { downloadBlob } from '@/utils/download'
import { errorCodeOf, financeErrorMessage } from '@/utils/payments/financeErrors'

const UNAVAILABLE_CODES = ['document.renderer_unavailable']

export function useSignedDownload() {
  const i18n = useI18n()
  const toast = useToast()
  const busy = reactive({})
  const unavailable = reactive({})

  async function download(key, requestLink) {
    if (busy[key]) return false
    busy[key] = true
    unavailable[key] = false
    try {
      const { data: link } = await requestLink()
      const { blob, fileName } = await filesService.fetchSignedFile(link)
      downloadBlob(blob, fileName)
      return true
    } catch (error) {
      if (UNAVAILABLE_CODES.includes(errorCodeOf(error))) unavailable[key] = true
      toast.error(financeErrorMessage(i18n, error, 'finance.downloads.failed'))
      return false
    } finally {
      busy[key] = false
    }
  }

  return { busy, unavailable, download }
}
