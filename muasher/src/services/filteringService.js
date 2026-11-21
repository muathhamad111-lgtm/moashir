import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toFilteringDetail, toFilteringMeta, toFilteringRow } from './mappers/award'

const { read, write } = domainRequests('filtering', 'filtering')

function mapData(request, mapper) {
  return request.then((envelope) => ({ ...envelope, data: mapper(envelope.data) }))
}

function base(cycleId, assessmentId) {
  return `/award/cycles/${cycleId}/filtering/${assessmentId}`
}

export const filteringService = {
  list(cycleId, params = {}) {
    return read(
      'list',
      () => apiClient.get(`/award/cycles/${cycleId}/filtering`, { params }),
      params,
    ).then((envelope) => ({
      ...envelope,
      data: (envelope.data ?? []).map(toFilteringRow),
      meta: toFilteringMeta(envelope.meta),
    }))
  },

  get(cycleId, assessmentId) {
    return mapData(
      read('get', () => apiClient.get(base(cycleId, assessmentId))),
      toFilteringDetail,
    )
  },

  qualify(cycleId, assessmentId, payload = {}) {
    return mapData(
      write(
        'qualify',
        () => apiClient.post(`${base(cycleId, assessmentId)}/qualify`, payload),
        payload,
      ),
      toFilteringRow,
    )
  },

  disqualify(cycleId, assessmentId, payload) {
    return mapData(
      write(
        'disqualify',
        () => apiClient.post(`${base(cycleId, assessmentId)}/disqualify`, payload),
        payload,
      ),
      toFilteringRow,
    )
  },
}
