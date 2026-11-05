import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import {
  toAssignedPathCount,
  toAssignedRow,
  toEligibleAssessor,
  toQueuePathCount,
  toQueueRow,
  toQueueStats,
  toRequestDetail,
} from './mappers/requests'
import { toWireRequestStatus } from './mappers/accreditedStatus'

const STATUS_FILTER = 'filter[status]'
const NOT_FOUND = 404
const UNPROCESSABLE = 422
const { read, write } = domainRequests('requests', 'request')

function mapData(request, mapper) {
  return request.then((envelope) => ({ ...envelope, data: mapper(envelope.data) }))
}

function queueParams(params) {
  if (!params[STATUS_FILTER]) return params
  return { ...params, [STATUS_FILTER]: toWireRequestStatus(params[STATUS_FILTER]) }
}

export const requestsService = {
  listQueue(params = {}) {
    const query = queueParams(params)
    return read('listQueue', () => apiClient.get('/requests', { params: query }), query).then(
      (envelope) => ({
        ...envelope,
        data: (envelope.data ?? []).map(toQueueRow),
        meta: {
          ...envelope.meta,
          stats: toQueueStats(envelope.meta?.stats),
          path: envelope.meta?.path ?? null,
          pathCounts: (envelope.meta?.pathCounts ?? []).map(toQueuePathCount),
        },
      }),
    )
  },

  get(requestId) {
    return mapData(
      read('get', () => apiClient.get(`/requests/${requestId}`)),
      toRequestDetail,
    )
  },

  listAssessors(params = {}) {
    const request = read(
      'listAssessors',
      () => apiClient.get('/requests/assessors', { params }),
      params,
      [NOT_FOUND, UNPROCESSABLE],
    )
    return mapData(request, (items) => (items ?? []).map(toEligibleAssessor))
  },

  assign(requestId, payload) {
    return mapData(
      write('assign', () => apiClient.post(`/requests/${requestId}/assign`, payload), payload, [
        NOT_FOUND,
      ]),
      toRequestDetail,
    )
  },

  reassign(requestId, payload) {
    return mapData(
      write('reassign', () => apiClient.post(`/requests/${requestId}/reassign`, payload), payload, [
        NOT_FOUND,
      ]),
      toRequestDetail,
    )
  },

  returnForCompletion(requestId, payload) {
    const request = write(
      'returnForCompletion',
      () => apiClient.post(`/requests/${requestId}/return-for-completion`, payload),
      payload,
    )
    return mapData(request, toRequestDetail)
  },

  withdraw(requestId, payload) {
    return mapData(
      write('withdraw', () => apiClient.post(`/requests/${requestId}/withdraw`, payload), payload),
      toRequestDetail,
    )
  },

  listAssigned(params = {}) {
    const request = read(
      'listAssigned',
      () => apiClient.get('/requests/assigned', { params }),
      params,
    )
    return request.then((envelope) => ({
      ...envelope,
      data: (envelope.data ?? []).map(toAssignedRow),
      meta: {
        ...envelope.meta,
        path: envelope.meta?.path ?? null,
        pathCounts: (envelope.meta?.pathCounts ?? []).map(toAssignedPathCount),
      },
    }))
  },
}
