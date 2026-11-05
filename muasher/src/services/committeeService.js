import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toCandidate } from './mappers/award'
import { toMember, toRosterOutcome } from './mappers/committee'

const { read, write } = domainRequests('committee', 'committee-roster')

function base(cycleId) {
  return `/award/cycles/${cycleId}/committee`
}

function memberPath(cycleId, memberId) {
  return `${base(cycleId)}/members/${memberId}`
}

function memberWrite(action, execute, payload) {
  return write(action, execute, payload).then((envelope) => ({
    ...envelope,
    data: toMember(envelope.data),
  }))
}

export const committeeService = {
  listMembers(cycleId) {
    return read('listMembers', () => apiClient.get(base(cycleId))).then((envelope) => ({
      ...envelope,
      data: (envelope.data ?? []).map(toMember),
    }))
  },

  listCandidates(cycleId, params = {}) {
    const request = read(
      'listCandidates',
      () => apiClient.get(`${base(cycleId)}/candidates`, { params }),
      params,
    )
    return request.then((envelope) => ({
      ...envelope,
      data: (envelope.data ?? []).map(toCandidate),
    }))
  },

  appoint(cycleId, payload) {
    return memberWrite(
      'appoint',
      () => apiClient.post(`${base(cycleId)}/members`, payload),
      payload,
    )
  },

  remove(cycleId, memberId, payload = {}) {
    return write(
      'remove',
      () => apiClient.delete(memberPath(cycleId, memberId), { data: payload }),
      payload,
    )
  },

  disable(cycleId, memberId, payload) {
    return memberWrite(
      'disable',
      () => apiClient.post(`${memberPath(cycleId, memberId)}/disable`, payload),
      payload,
    )
  },

  exclude(cycleId, memberId, payload = {}) {
    const request = write(
      'exclude',
      () => apiClient.post(`${memberPath(cycleId, memberId)}/exclude`, payload),
      payload,
    )
    return request.then((envelope) => ({
      ...envelope,
      data: toMember(envelope.data),
      meta: toRosterOutcome(envelope.meta),
    }))
  },

  substitute(cycleId, memberId, payload) {
    return memberWrite(
      'substitute',
      () => apiClient.post(`${memberPath(cycleId, memberId)}/substitute`, payload),
      payload,
    )
  },
}
