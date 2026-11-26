import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import {
  toBulkOutcome,
  toBulkPreview,
  toFilteringDetail,
  toFilteringMeta,
  toFilteringRow,
} from './mappers/award'

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

  // «تأهيل كل من بلغ حد التأهل»: the preview is read by deciders only, so 403/404 just hide the action.
  eligible(cycleId) {
    return read(
      'eligible',
      () => apiClient.get(`/award/cycles/${cycleId}/filtering/qualify-eligible`),
      undefined,
      [403, 404],
    ).then((envelope) => ({ ...envelope, data: toBulkPreview(envelope.data) }))
  },

  qualifyEligible(cycleId, payload = {}) {
    return write(
      'qualifyEligible',
      () => apiClient.post(`/award/cycles/${cycleId}/filtering/qualify-eligible`, payload),
      payload,
    ).then((envelope) => ({ ...envelope, data: toBulkOutcome(envelope.data) }))
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
