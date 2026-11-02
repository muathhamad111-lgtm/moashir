import { payloadShape } from '@/utils/payloadShape'
import { apiClient } from '@/api/client'
import { serviceRequest } from './serviceRequest'

export const feedbackReportService = {
  get(assessmentId) {
    return serviceRequest(
      'feedbackReport.get',
      () => apiClient.get(`/assessments/${assessmentId}/feedback-report`),
      { entity: 'feedback-report' },
    )
  },

  export(assessmentId, payload = {}) {
    return serviceRequest(
      'feedbackReport.export',
      () => apiClient.post(`/assessments/${assessmentId}/feedback-report/export`, payload),
      { entity: 'feedback-report', payloadShape: payloadShape(payload) },
    )
  },
}
