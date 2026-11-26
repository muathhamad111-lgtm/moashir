import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { withJsonErrorBody } from './mappers/blobError'
import { toDownload } from './mappers/download'
import {
  toNdaAcceptance,
  toNdaOverview,
  toNdaRegisterRow,
  toNdaRegisterSummary,
  toNdaText,
  toNdaVersion,
} from './mappers/nda'

const ADMIN = '/settings/nda'
const LIST_LIMIT = 50
const EXPORT_FALLBACK_NAME = 'nda-acceptances.csv'

const { read, write } = domainRequests('nda', 'nda')

function mapped(request, mapper) {
  return request.then((envelope) => ({ ...envelope, data: mapper(envelope.data) }))
}

function mappedList(request, mapper) {
  return request.then((envelope) => ({ ...envelope, data: (envelope.data ?? []).map(mapper) }))
}

export const ndaService = {
  mine() {
    return read('mine', () => apiClient.get('/me/nda')).then((envelope) => ({
      ...envelope,
      data: {
        acceptanceRequired: envelope.data?.acceptanceRequired === true,
        version: toNdaText(envelope.data?.version),
      },
    }))
  },

  accept(termsVersionId) {
    const payload = { termsVersionId }
    return write('accept', () => apiClient.post('/me/nda/acceptances', payload), payload)
  },

  myAcceptances(params = { perPage: LIST_LIMIT }) {
    return mappedList(
      read('myAcceptances', () => apiClient.get('/me/nda/acceptances', { params }), params),
      toNdaAcceptance,
    )
  },

  myVersion(versionId) {
    return mapped(
      read('myVersion', () => apiClient.get(`/me/nda/versions/${versionId}`)),
      toNdaText,
    )
  },

  userAcceptances(userId, params = { perPage: LIST_LIMIT }) {
    return mappedList(
      read(
        'userAcceptances',
        () => apiClient.get(`/users/${userId}/nda-acceptances`, { params }),
        params,
      ),
      toNdaAcceptance,
    )
  },

  overview() {
    return mapped(
      read('overview', () => apiClient.get(ADMIN)),
      toNdaOverview,
    )
  },

  updateSettings(payload) {
    return mapped(
      write('updateSettings', () => apiClient.put(ADMIN, payload), payload),
      toNdaOverview,
    )
  },

  versions() {
    const params = { perPage: LIST_LIMIT }
    return mappedList(
      read('versions', () => apiClient.get(`${ADMIN}/versions`, { params }), params),
      toNdaVersion,
    )
  },

  version(versionId) {
    return mapped(
      read('version', () => apiClient.get(`${ADMIN}/versions/${versionId}`)),
      toNdaVersion,
    )
  },

  createDraft(payload) {
    return mapped(
      write('createDraft', () => apiClient.post(`${ADMIN}/draft`, payload), payload),
      toNdaVersion,
    )
  },

  updateDraft(payload) {
    return mapped(
      write('updateDraft', () => apiClient.put(`${ADMIN}/draft`, payload), payload),
      toNdaVersion,
    )
  },

  discardDraft() {
    return write('discardDraft', () => apiClient.delete(`${ADMIN}/draft`), {})
  },

  impact() {
    return read('impact', () => apiClient.get(`${ADMIN}/draft/impact`))
  },

  publish(payload) {
    return mapped(
      write('publish', () => apiClient.post(`${ADMIN}/draft/publish`, payload), payload),
      toNdaVersion,
    )
  },

  acceptances(params = {}) {
    return read(
      'acceptances',
      () => apiClient.get(`${ADMIN}/acceptances`, { params }),
      params,
    ).then((envelope) => ({
      ...envelope,
      data: (envelope.data ?? []).map(toNdaRegisterRow),
      meta: {
        ...envelope.meta,
        summary: toNdaRegisterSummary(envelope.meta?.summary),
        version: envelope.meta?.version ? toNdaVersion(envelope.meta.version) : null,
      },
    }))
  },

  exportAcceptances(params = {}) {
    const execute = () =>
      withJsonErrorBody(
        apiClient.get(`${ADMIN}/acceptances/export`, { params, responseType: 'blob' }),
      ).then((response) => toDownload(response, EXPORT_FALLBACK_NAME))
    return read('exportAcceptances', execute, params)
  },

  sendReminders() {
    return write('sendReminders', () => apiClient.post(`${ADMIN}/reminders`, {}), {})
  },
}

// The draft calls in the shape `useLegalVersions` expects, so the agreement reuses the legal draft editor.
export const ndaDraftVersions = {
  list: () => ndaService.versions(),
  get: (versionId) => ndaService.version(versionId),
  createDraft: (_kind, payload) => ndaService.createDraft(payload),
  updateDraft: (_versionId, payload) => ndaService.updateDraft(payload),
  discardDraft: () => ndaService.discardDraft(),
  publish: (_versionId, payload) => ndaService.publish(payload),
}
