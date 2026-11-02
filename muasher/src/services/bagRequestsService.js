import { payloadShape } from '@/utils/payloadShape'
import { apiClient } from '@/api/client'
import { serviceRequest } from './serviceRequest'

export const bagRequestsService = {
  list(params = {}) {
    return serviceRequest(
      'bagRequests.list',
      () => apiClient.get('/bag/requests', { params }),
      { entity: 'bagRequests' },
    )
  },

  listEvaluators(params = {}) {
    return serviceRequest(
      'bagRequests.listEvaluators',
      () => apiClient.get('/bag/requests/evaluators', { params }),
      { entity: 'bagRequests' },
    )
  },

  listForEvaluator(evaluatorId, params = {}) {
    return serviceRequest(
      'bagRequests.listForEvaluator',
      () => apiClient.get('/bag/evaluator/requests', { params: { ...params, evaluatorId } }),
      { entity: 'bagRequests' },
    )
  },

  get(requestId) {
    return serviceRequest(
      'bagRequests.get',
      () => apiClient.get(`/bag/requests/${requestId}`),
      { entity: 'bagRequests' },
    )
  },

  assign(requestId, payload) {
    return serviceRequest(
      'bagRequests.assign',
      () => apiClient.post(`/bag/requests/${requestId}/assign`, payload),
      { entity: 'bagRequests', payloadShape: payloadShape(payload) },
    )
  },

  reassign(requestId, payload) {
    return serviceRequest(
      'bagRequests.reassign',
      () => apiClient.post(`/bag/requests/${requestId}/reassign`, payload),
      { entity: 'bagRequests', payloadShape: payloadShape(payload) },
    )
  },
}
