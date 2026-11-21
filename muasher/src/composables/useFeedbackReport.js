import { onMounted, ref, unref } from 'vue'
import { feedbackReportService } from '@/services/feedbackReportService'

const NOT_FOUND = 404

export function useFeedbackReport(assessmentId) {
  const status = ref('loading')
  const errorMessage = ref('')
  const report = ref(null)

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data } = await feedbackReportService.get(unref(assessmentId))
      report.value = data
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = error?.normalized?.status === NOT_FOUND ? 'missing' : 'error'
    }
  }

  onMounted(load)

  return { status, errorMessage, report, load }
}
