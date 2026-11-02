import { payloadShape } from '@/utils/payloadShape'
import { apiClient } from '@/api/client'
import { serviceRequest } from './serviceRequest'

export const awardResultsService = {
  listResults() {
    return serviceRequest(
      'awardResults.listResults',
      () => apiClient.get('/award/results'),
      { entity: 'award-results' },
    )
  },

  setRegionalRank(assessmentId, rank) {
    const payload = { rank }
    return serviceRequest(
      'awardResults.setRegionalRank',
      () => apiClient.post(`/award/results/${assessmentId}/rank`, payload),
      { entity: 'award-results', payloadShape: payloadShape(payload) },
    )
  },

  approveRegion(region) {
    return serviceRequest(
      'awardResults.approveRegion',
      () => apiClient.post(`/award/results/region/${region}/approve`),
      { entity: 'award-results' },
    )
  },

  listNational() {
    return serviceRequest(
      'awardResults.listNational',
      () => apiClient.get('/award/national'),
      { entity: 'award-results' },
    )
  },

  setNationalRank(assessmentId, rank) {
    const payload = { rank }
    return serviceRequest(
      'awardResults.setNationalRank',
      () => apiClient.post(`/award/national/${assessmentId}/rank`, payload),
      { entity: 'award-results', payloadShape: payloadShape(payload) },
    )
  },

  approveNational() {
    return serviceRequest(
      'awardResults.approveNational',
      () => apiClient.post('/award/national/approve'),
      { entity: 'award-results' },
    )
  },
}
