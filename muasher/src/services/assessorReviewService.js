import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toAssessment, toAssessmentScores } from './mappers/assessments'
import { toAssessorReview, toFeedbackSaved } from './mappers/review'

const { read, write } = domainRequests('review', 'assessment')

function mapData(request, mapper) {
  return request.then((envelope) => ({ ...envelope, data: mapper(envelope.data) }))
}

export const assessorReviewService = {
  getReview(assessmentId) {
    return mapData(read('getReview', () => apiClient.get(`/assessments/${assessmentId}/review`)), toAssessorReview)
  },

  startReview(assessmentId, payload = {}) {
    const request = write('startReview', () => apiClient.post(`/assessments/${assessmentId}/start-review`, payload), payload)
    return mapData(request, toAssessment)
  },

  saveScores(assessmentId, payload) {
    const request = write('saveScores', () => apiClient.put(`/assessments/${assessmentId}/review/scores`, payload), payload)
    return mapData(request, toAssessmentScores)
  },

  saveFeedback(assessmentId, payload) {
    const request = write('saveFeedback', () => apiClient.put(`/assessments/${assessmentId}/review/feedback`, payload), payload)
    return mapData(request, toFeedbackSaved)
  },

  requestCompletion(assessmentId, payload) {
    const request = write('requestCompletion', () => apiClient.post(`/assessments/${assessmentId}/request-completion`, payload), payload)
    return mapData(request, toAssessment)
  },

  complete(assessmentId, payload = {}) {
    const request = write('complete', () => apiClient.post(`/assessments/${assessmentId}/complete`, payload), payload)
    return mapData(request, toAssessment)
  },
}
