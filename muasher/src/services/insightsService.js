import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const { read, write } = domainRequests('insights', 'insights')

const TOOL_REJECTIONS = [404, 409]
const EXPORT_REJECTIONS = [403, 404, 503]

function entityPath(entityId) {
  return entityId ? `/entities/${entityId}` : '/entities/me'
}

export const insightsService = {
  overview(entityId = null) {
    return read('overview', () => apiClient.get(`${entityPath(entityId)}/insights`))
  },

  results(entityId = null, params = {}) {
    return read(
      'results',
      () => apiClient.get(`${entityPath(entityId)}/insights/results`, { params }),
      params,
    )
  },

  library(entityId = null, params = {}) {
    return read(
      'library',
      () => apiClient.get(`${entityPath(entityId)}/report-library`, { params }),
      params,
    )
  },

  strengthsGaps(assessmentId) {
    return read(
      'strengthsGaps',
      () => apiClient.get(`/assessments/${assessmentId}/insights/strengths-gaps`),
      null,
      TOOL_REJECTIONS,
    )
  },

  maturity(assessmentId) {
    return read(
      'maturity',
      () => apiClient.get(`/assessments/${assessmentId}/insights/maturity`),
      null,
      TOOL_REJECTIONS,
    )
  },

  executiveSummary(assessmentId) {
    return read(
      'executiveSummary',
      () => apiClient.get(`/assessments/${assessmentId}/insights/executive-summary`),
      null,
      TOOL_REJECTIONS,
    )
  },

  exportBoardReport(assessmentId, payload) {
    return write(
      'exportBoardReport',
      () => apiClient.post(`/assessments/${assessmentId}/board-report/export`, payload),
      payload,
      EXPORT_REJECTIONS,
    )
  },
}
