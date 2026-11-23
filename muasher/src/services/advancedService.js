import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toAdvancedAssessment, toAdvancedCurrent } from './mappers/award'

const { read, write } = domainRequests('advanced', 'assessment')

function mapData(request, mapper) {
  return request.then((envelope) => ({ ...envelope, data: mapper(envelope.data) }))
}

export const advancedService = {
  getCurrent(cycleId = null) {
    const params = cycleId ? { awardCycleId: cycleId } : {}
    return mapData(
      read('getCurrent', () => apiClient.get('/assessments/advanced/current', { params }), params),
      toAdvancedCurrent,
    )
  },

  apply(payload) {
    return mapData(
      write('apply', () => apiClient.post('/assessments/advanced', payload), payload),
      toAdvancedAssessment,
    )
  },

  deleteDraft(assessmentId, payload = {}) {
    return write(
      'deleteDraft',
      () => apiClient.delete(`/assessments/${assessmentId}`, { data: payload }),
      payload,
    )
  },
}
