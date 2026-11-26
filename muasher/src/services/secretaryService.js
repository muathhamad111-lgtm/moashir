import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import {
  toDiscussion,
  toSecretaryDetail,
  toSecretaryEvaluation,
  toSecretaryMeta,
  toSecretaryRow,
} from './mappers/committee'

const { read, write } = domainRequests('secretary', 'secretary-desk')

function base(cycleId) {
  return `/award/cycles/${cycleId}/secretary/requests`
}

function requestPath(cycleId, assessmentId) {
  return `${base(cycleId)}/${assessmentId}`
}

function mapData(request, mapper) {
  return request.then((envelope) => ({ ...envelope, data: mapper(envelope.data) }))
}

function decide(action, cycleId, assessmentId, verb, payload) {
  return mapData(
    write(
      action,
      () => apiClient.post(`${requestPath(cycleId, assessmentId)}/${verb}`, payload),
      payload,
    ),
    toSecretaryDetail,
  )
}

export const secretaryService = {
  list(cycleId, params = {}) {
    return read('list', () => apiClient.get(base(cycleId), { params }), params).then(
      (envelope) => ({
        ...envelope,
        data: (envelope.data ?? []).map(toSecretaryRow),
        meta: toSecretaryMeta(envelope.meta),
      }),
    )
  },

  get(cycleId, assessmentId) {
    return mapData(
      read('get', () => apiClient.get(requestPath(cycleId, assessmentId))),
      toSecretaryDetail,
    )
  },

  getEvaluation(cycleId, assessmentId, assignmentId) {
    const request = read('getEvaluation', () =>
      apiClient.get(`${requestPath(cycleId, assessmentId)}/evaluations/${assignmentId}`),
    )
    return mapData(request, toSecretaryEvaluation)
  },

  recomputeAverage(cycleId, assessmentId, payload = {}) {
    return decide('recomputeAverage', cycleId, assessmentId, 'average', payload)
  },

  approve(cycleId, assessmentId, payload = {}) {
    return decide('approve', cycleId, assessmentId, 'approve', payload)
  },

  returnToCommittee(cycleId, assessmentId, payload) {
    return decide('returnToCommittee', cycleId, assessmentId, 'return', payload)
  },

  approveSubstitution(cycleId, assessmentId, substitutionId, payload = {}) {
    return decide(
      'approveSubstitution',
      cycleId,
      assessmentId,
      `substitutions/${substitutionId}/approve`,
      payload,
    )
  },

  openDiscussion(cycleId, assessmentId, payload) {
    const request = write(
      'openDiscussion',
      () => apiClient.post(`${requestPath(cycleId, assessmentId)}/discussions`, payload),
      payload,
    )
    return mapData(request, toDiscussion)
  },

  closeDiscussion(cycleId, assessmentId, discussionId, payload = {}) {
    const path = `${requestPath(cycleId, assessmentId)}/discussions/${discussionId}/close`
    return mapData(
      write('closeDiscussion', () => apiClient.post(path, payload), payload),
      toDiscussion,
    )
  },
}
