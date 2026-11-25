import { apiClient } from '@/api/client'
import { toSnakeKey } from '@/api/caseMapping'
import { domainRequests } from './domainRequest'
import { withJsonErrorBody } from './mappers/blobError'
import { toDownload } from './mappers/download'
import { withOwnEntity } from './mappers/ownEntity'

const EXPORT_FALLBACK_NAME = 'entities.csv'
const NOT_FOUND = 404

const { read, write } = domainRequests('entities', 'entity')

export const entitiesService = {
  getMine() {
    return read('getMine', () => apiClient.get('/entities/me')).then(withOwnEntity)
  },
  saveProfileSection(section, payload) {
    return write(
      'saveProfileSection',
      () => apiClient.patch(`/entities/me/profile/${encodeURIComponent(section)}`, payload),
      payload,
    ).then(withOwnEntity)
  },
  uploadLogo(file) {
    const body = new FormData()
    body.append('file', file)
    return write('uploadLogo', () => apiClient.post('/entities/me/logo', body), {
      file: file.type,
    }).then(withOwnEntity)
  },
  removeLogo() {
    return write('removeLogo', () => apiClient.delete('/entities/me/logo'), {}).then(withOwnEntity)
  },
  uploadVatCertificate(file) {
    const body = new FormData()
    body.append('file', file)
    return write(
      'uploadVatCertificate',
      () => apiClient.post('/entities/me/vat-certificate', body),
      {
        file: file.type,
      },
    ).then(withOwnEntity)
  },
  removeVatCertificate() {
    return write(
      'removeVatCertificate',
      () => apiClient.delete('/entities/me/vat-certificate'),
      {},
    ).then(withOwnEntity)
  },
  requestVatCertificateDownload() {
    return write(
      'requestVatCertificateDownload',
      () => apiClient.post('/entities/me/vat-certificate/download'),
      {},
      [NOT_FOUND],
    )
  },
  uploadOwnDocument(document, file) {
    const body = new FormData()
    body.append('file', file)
    return write(
      'uploadOwnDocument',
      () => apiClient.post(`/entities/me/documents/${toSnakeKey(document)}`, body),
      { document, file: file.type },
    ).then(withOwnEntity)
  },
  requestOwnDocumentDownload(document) {
    return write(
      'requestOwnDocumentDownload',
      () => apiClient.post(`/entities/me/documents/${toSnakeKey(document)}/download`),
      { document },
      [NOT_FOUND],
    )
  },
  requestDocumentDownload(id, document) {
    return write(
      'requestDocumentDownload',
      () => apiClient.post(`/entities/${id}/documents/${toSnakeKey(document)}/download`),
      { document },
      [NOT_FOUND],
    )
  },
  cancelEmailChange() {
    return write(
      'cancelEmailChange',
      () => apiClient.post('/entities/me/email/cancel', {}),
      {},
    ).then(withOwnEntity)
  },
  requestEmailChange(newEmail) {
    const payload = { newEmail }
    return write(
      'requestEmailChange',
      () => apiClient.post('/entities/me/email/change', payload),
      payload,
    )
  },
  confirmEmailChange({ challengeId, code }) {
    const payload = { challengeId, code }
    return write(
      'confirmEmailChange',
      () => apiClient.post('/entities/me/email/confirm', payload),
      payload,
    )
  },
  list(params = {}) {
    return read('list', () => apiClient.get('/entities', { params }), params)
  },
  get(id) {
    return read('get', () => apiClient.get(`/entities/${id}`))
  },
  // The staff record's «سجل الحساب»: the account events, profile changes and e-mail changes, newest first.
  timeline(id, params = {}) {
    return read('timeline', () => apiClient.get(`/entities/${id}/timeline`, { params }), params)
  },
  updateOnBehalf(id, payload) {
    return write('updateOnBehalf', () => apiClient.put(`/entities/${id}`, payload), payload)
  },
  exportCsv(params = {}) {
    const execute = () =>
      withJsonErrorBody(
        apiClient.post('/entities/export', null, { params, responseType: 'blob' }),
      ).then((response) => toDownload(response, EXPORT_FALLBACK_NAME))
    return write('exportCsv', execute, params)
  },
}
