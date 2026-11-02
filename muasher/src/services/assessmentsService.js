import { apiClient } from '@/api/client'
import { payloadShape } from '@/utils/payloadShape'
import { domainRequests } from './domainRequest'
import { serviceRequest } from './serviceRequest'
import { toAssessment, toAssessmentForm, toAssessmentRecord, toAssessmentResult, toAssessmentScores } from './mappers/assessments'

const SELF_PATH = 'self'
const NOT_FOUND = 404

const { read, write } = domainRequests('assessments', 'assessment')

function mapData(request, mapper) {
  return request.then((envelope) => ({ ...envelope, data: mapper(envelope.data) }))
}

export const assessmentsService = {
  getPublishedCriteria(params = {}) {
    return serviceRequest(
      'assessments.getPublishedCriteria',
      () => apiClient.get('/assessments/criteria', { params }),
      { entity: 'assessment', paramsShape: payloadShape(params) },
      { expectedStatuses: [NOT_FOUND] },
    )
  },

  list(params = {}) {
    const request = read('list', () => apiClient.get('/assessments', { params }), params)
    return mapData(request, (items) => (items ?? []).map(toAssessment))
  },

  get(assessmentId) {
    return mapData(read('get', () => apiClient.get(`/assessments/${assessmentId}`)), toAssessmentRecord)
  },

  startSelf() {
    const payload = { path: SELF_PATH }
    return mapData(write('startSelf', () => apiClient.post('/assessments', payload), payload), toAssessment)
  },

  getForm(assessmentId) {
    return mapData(read('getForm', () => apiClient.get(`/assessments/${assessmentId}/form`)), toAssessmentForm)
  },

  saveDraft(assessmentId, payload) {
    const request = write('saveDraft', () => apiClient.put(`/assessments/${assessmentId}`, payload), payload)
    return mapData(request, toAssessmentScores)
  },

  submit(assessmentId, payload = {}) {
    const request = write('submit', () => apiClient.post(`/assessments/${assessmentId}/submit`, payload), payload)
    return mapData(request, toAssessmentResult)
  },

  getResult(assessmentId) {
    return mapData(read('getResult', () => apiClient.get(`/assessments/${assessmentId}/result`)), toAssessmentResult)
  },

  getAdvancedResult(assessmentId) {
    return serviceRequest(
      'assessments.getAdvancedResult',
      () => apiClient.get(`/assessments/${assessmentId}/advanced-result`),
      { entity: 'assessment' },
    )
  },
}
