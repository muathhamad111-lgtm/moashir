import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toResultRow, toResultsMeta } from './mappers/committee'

const { read, write } = domainRequests('awardResults', 'award-results')

function cyclePath(cycleId) {
  return `/award/cycles/${cycleId}`
}

function board(request) {
  return request.then((envelope) => ({
    ...envelope,
    data: (envelope.data ?? []).map(toResultRow),
    meta: toResultsMeta(envelope.meta),
  }))
}

function row(request) {
  return request.then((envelope) => ({ ...envelope, data: toResultRow(envelope.data) }))
}

export const awardResultsService = {
  listRegional(cycleId, params = {}) {
    return board(
      read(
        'listRegional',
        () => apiClient.get(`${cyclePath(cycleId)}/results`, { params }),
        params,
      ),
    )
  },

  setRegionalRank(cycleId, assessmentId, payload) {
    return row(
      write(
        'setRegionalRank',
        () => apiClient.put(`${cyclePath(cycleId)}/results/${assessmentId}/regional-rank`, payload),
        payload,
      ),
    )
  },

  approveRegion(cycleId, regionId, payload = {}) {
    return board(
      write(
        'approveRegion',
        () => apiClient.post(`${cyclePath(cycleId)}/regions/${regionId}/approve`, payload),
        payload,
      ),
    )
  },

  unapproveRegion(cycleId, regionId, payload) {
    return board(
      write(
        'unapproveRegion',
        () => apiClient.post(`${cyclePath(cycleId)}/regions/${regionId}/unapprove`, payload),
        payload,
      ),
    )
  },

  listNational(cycleId) {
    return board(read('listNational', () => apiClient.get(`${cyclePath(cycleId)}/national`)))
  },

  setNationalRank(cycleId, assessmentId, payload) {
    return row(
      write(
        'setNationalRank',
        () => apiClient.put(`${cyclePath(cycleId)}/national/${assessmentId}/rank`, payload),
        payload,
      ),
    )
  },

  approveNational(cycleId, payload = {}) {
    return board(
      write(
        'approveNational',
        () => apiClient.post(`${cyclePath(cycleId)}/national/approve`, payload),
        payload,
      ),
    )
  },
}
