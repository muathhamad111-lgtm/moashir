import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { withJsonErrorBody } from './mappers/blobError'
import { toDownload } from './mappers/download'

const PATH = '/audit-logs'
const EXPORT_FALLBACK_NAME = 'audit-log.csv'
const NOT_FOUND = 404

const { read, write } = domainRequests('auditLog', 'audit-log')

export function toAuditEntry(raw = {}) {
  return {
    id: raw.id ?? null,
    event: raw.event ?? '',
    actor: {
      kind: raw.actor?.kind ?? 'system',
      id: raw.actor?.id ?? null,
      name: raw.actor?.name ?? null,
      roles: raw.actor?.roles ?? [],
    },
    subject: raw.subject ? { type: raw.subject.type ?? '', id: raw.subject.id ?? null } : null,
    oldValues: raw.oldValues ?? null,
    newValues: raw.newValues ?? null,
    reason: raw.reason ?? null,
    tags: raw.tags ?? [],
    ipAddress: raw.ipAddress ?? null,
    userAgent: raw.userAgent ?? null,
    url: raw.url ?? null,
    requestId: raw.requestId ?? null,
    createdAt: raw.createdAt ?? null,
  }
}

export const auditLogService = {
  list(params = {}) {
    return read('list', () => apiClient.get(PATH, { params }), params).then((envelope) => ({
      ...envelope,
      data: (envelope.data ?? []).map(toAuditEntry),
    }))
  },

  get(entryId) {
    return read('get', () => apiClient.get(`${PATH}/${entryId}`), null, [NOT_FOUND]).then(
      (envelope) => ({ ...envelope, data: toAuditEntry(envelope.data) }),
    )
  },

  exportCsv(payload) {
    const execute = () =>
      withJsonErrorBody(apiClient.post(`${PATH}/export`, payload, { responseType: 'blob' })).then(
        (response) => toDownload(response, EXPORT_FALLBACK_NAME),
      )
    return write('exportCsv', execute, payload)
  },
}
