import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const BASE = '/admin/content/legal'
const LIST_LIMIT = 50

const { read, write } = domainRequests('legalVersions', 'legal-version')

function localized(raw) {
  return { ar: raw?.ar ?? '', en: raw?.en ?? '' }
}

export function toLegalVersion(raw = {}) {
  return {
    id: raw.id ?? null,
    kind: raw.kind ?? null,
    version: raw.version ?? '',
    status: raw.status ?? 'draft',
    title: localized(raw.title),
    body: raw.body ? localized(raw.body) : null,
    effectiveFrom: raw.effectiveFrom ?? null,
    publishedAt: raw.publishedAt ?? null,
    createdAt: raw.createdAt ?? null,
    updatedAt: raw.updatedAt ?? null,
  }
}

function mapVersion(request) {
  return request.then((envelope) => ({ ...envelope, data: toLegalVersion(envelope.data) }))
}

export const legalVersionsService = {
  list(kind) {
    const params = { perPage: LIST_LIMIT }
    return read('list', () => apiClient.get(`${BASE}/${kind}/versions`, { params }), params).then(
      (envelope) => ({ ...envelope, data: (envelope.data ?? []).map(toLegalVersion) }),
    )
  },

  get(versionId) {
    return mapVersion(read('get', () => apiClient.get(`${BASE}/versions/${versionId}`)))
  },

  createDraft(kind, payload) {
    return mapVersion(
      write('createDraft', () => apiClient.post(`${BASE}/${kind}/versions`, payload), payload),
    )
  },

  updateDraft(versionId, payload) {
    return mapVersion(
      write('updateDraft', () => apiClient.put(`${BASE}/versions/${versionId}`, payload), payload),
    )
  },

  discardDraft(versionId) {
    return write('discardDraft', () => apiClient.delete(`${BASE}/versions/${versionId}`), {})
  },

  publish(versionId, payload = {}) {
    return mapVersion(
      write(
        'publish',
        () => apiClient.post(`${BASE}/versions/${versionId}/publish`, payload),
        payload,
      ),
    )
  },
}
