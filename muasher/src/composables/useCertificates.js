import { reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { certificatesService } from '@/services/certificatesService'

export function useCertificates() {
  const { locale } = useI18n()

  const status = ref('loading')
  const errorMessage = ref('')
  const certificates = ref([])
  const downloading = reactive({})

  function localized(content) {
    if (content === null || content === undefined) return ''
    if (typeof content === 'string') return content
    return content[locale.value] || content.ar || content.en || ''
  }

  async function load(params = {}) {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data } = await certificatesService.list(params)
      certificates.value = data ?? []
      status.value = certificates.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  async function download(certificateId) {
    if (downloading[certificateId]) return null
    downloading[certificateId] = true
    try {
      const { data } = await certificatesService.download(certificateId)
      return data
    } finally {
      downloading[certificateId] = false
    }
  }

  return { status, errorMessage, certificates, downloading, localized, load, download }
}
