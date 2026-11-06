import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toAccreditedCurrent, toAccreditedResult } from './mappers/accredited'

const { read } = domainRequests('accredited', 'assessment')

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

  getResult(assessmentId) {
    return mapData(
      read('getResult', () => apiClient.get(`/assessments/${assessmentId}/result`)),
      toAccreditedResult,
    )
  },
}
