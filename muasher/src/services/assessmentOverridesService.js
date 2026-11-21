import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toAssessment } from './mappers/assessments'
import { toWireRequestStatus } from './mappers/accreditedStatus'
import { toOverridePreview } from './mappers/review'

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

  // The total and level the edited scores would give, computed by the server without saving.
  previewScores(assessmentId, payload) {
    return write(
      'previewScores',
      () => apiClient.post(`/assessments/${assessmentId}/scores/override/preview`, payload),
      payload,
    ).then((envelope) => ({ ...envelope, data: toOverridePreview(envelope.data) }))
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
