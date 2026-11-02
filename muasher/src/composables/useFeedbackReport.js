import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { feedbackReportService } from '@/services/feedbackReportService'

export function useFeedbackReport(assessmentId) {
  const { locale } = useI18n()

  const status = ref('loading')
  const errorMessage = ref('')
  const report = ref(null)
  const exporting = ref(false)

  function localized(content) {
    if (content === null || content === undefined) return ''
    if (typeof content === 'string') return content
    return content[locale.value] || content.ar || content.en || ''
  }

  const isAvailable = computed(() => report.value?.status === 'available')

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data } = await feedbackReportService.get(assessmentId)
      report.value = data
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  async function exportReport() {
    if (exporting.value) return null
    exporting.value = true
    try {
      const { data } = await feedbackReportService.export(assessmentId)
      return data
    } finally {
      exporting.value = false
    }
  }

  return { status, errorMessage, report, exporting, isAvailable, localized, load, exportReport }
}
