import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toAssessment } from './mappers/assessments'
import { toWireRequestStatus } from './mappers/accreditedStatus'

const { write } = domainRequests('overrides', 'assessment')

function mapData(request) {
  return request.then((envelope) => ({ ...envelope, data: toAssessment(envelope.data) }))
}

export const assessmentOverridesService = {
  overrideScores(assessmentId, payload) {
    return mapData(
      write(
        'overrideScores',
        () => apiClient.post(`/assessments/${assessmentId}/scores/override`, payload),
        payload,
      ),
    )
  },

  reopen(assessmentId, payload) {
    return mapData(
      write(
        'reopen',
        () => apiClient.post(`/assessments/${assessmentId}/reopen`, payload),
        payload,
      ),
    )
  },

  forceTransition(assessmentId, { toStatus, reason }) {
    const payload = { toStatus: toWireRequestStatus(toStatus), reason }
    return mapData(
      write(
        'forceTransition',
        () => apiClient.post(`/assessments/${assessmentId}/transition/force`, payload),
        payload,
      ),
    )
  },
}
