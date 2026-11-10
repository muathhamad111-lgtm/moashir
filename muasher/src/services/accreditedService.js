import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toAccreditedCurrent, toAccreditedResult } from './mappers/accredited'
import { toAssessment } from './mappers/assessments'

const { read, write } = domainRequests('accredited', 'assessment')

function mapData(request, mapper) {
  return request.then((envelope) => ({ ...envelope, data: mapper(envelope.data) }))
}

export const accreditedService = {
  getCurrent() {
    return mapData(
      read('getCurrent', () => apiClient.get('/assessments/accredited/current')),
      toAccreditedCurrent,
    )
  },

  startFree() {
    return mapData(
      write('startFree', () => apiClient.post('/assessments/accredited'), {}),
      toAssessment,
    )
  },

  getResult(assessmentId) {
    return mapData(
      read('getResult', () => apiClient.get(`/assessments/${assessmentId}/result`)),
      toAccreditedResult,
    )
  },
}
