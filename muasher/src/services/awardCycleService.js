import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toCandidate, toCycle, toCycleDefaults, toCycleDetail, toCycleStats, toPublicCycle } from './mappers/award'

const { read, write } = domainRequests('awardCycles', 'award-cycle')

function mapData(request, mapper) {
  return request.then((envelope) => ({ ...envelope, data: mapper(envelope.data) }))
}

function transition(action, cycleId, verb, payload) {
  const request = write(action, () => apiClient.post(`/award/cycles/${cycleId}/${verb}`, payload), payload)
  return mapData(request, toCycleDetail)
}

export const awardCycleService = {
  list(params = {}) {
    return read('list', () => apiClient.get('/award/cycles', { params }), params).then((envelope) => ({
      ...envelope,
      data: (envelope.data ?? []).map(toCycle),
      meta: { ...envelope.meta, stats: toCycleStats(envelope.meta?.stats) },
    }))
  },

  get(cycleId) {
    return mapData(read('get', () => apiClient.get(`/award/cycles/${cycleId}`)), toCycleDetail)
  },

  getCurrent() {
    return mapData(read('getCurrent', () => apiClient.get('/award/cycles/current')), toPublicCycle)
  },

  getDefaultConditions() {
    return mapData(read('getDefaultConditions', () => apiClient.get('/award/cycles/default-conditions')), toCycleDefaults)
  },

  listCandidates(params) {
    const request = read('listCandidates', () => apiClient.get('/award/cycles/candidates', { params }), params)
    return mapData(request, (items) => (items ?? []).map(toCandidate))
  },

  create(payload) {
    return mapData(write('create', () => apiClient.post('/award/cycles', payload), payload), toCycleDetail)
  },

  update(cycleId, payload) {
    return mapData(write('update', () => apiClient.put(`/award/cycles/${cycleId}`, payload), payload), toCycleDetail)
  },

  syncMembers(cycleId, payload) {
    return mapData(write('syncMembers', () => apiClient.put(`/award/cycles/${cycleId}/members`, payload), payload), toCycleDetail)
  },

  syncInitialAssessors(cycleId, payload) {
    const request = write('syncInitialAssessors', () => apiClient.put(`/award/cycles/${cycleId}/initial-assessors`, payload), payload)
    return mapData(request, toCycleDetail)
  },

  open(cycleId, payload = {}) {
    return transition('open', cycleId, 'open', payload)
  },

  closeSubmissions(cycleId, payload = {}) {
    return transition('closeSubmissions', cycleId, 'close-submissions', payload)
  },

  close(cycleId, payload = {}) {
    return transition('close', cycleId, 'close', payload)
  },

  reopen(cycleId, payload) {
    return transition('reopen', cycleId, 'reopen', payload)
  },
}
