import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { withJsonErrorBody } from './mappers/blobError'
import { toDownload } from './mappers/download'

const EXPORT_FALLBACK_NAME = 'entities.csv'

const { read, write } = domainRequests('entities', 'entity')

export const entitiesService = {
  getMine() {
    return read('getMine', () => apiClient.get('/entities/me'))
  },
  updateMine(payload) {
    return write('updateMine', () => apiClient.put('/entities/me', payload), payload)
  },
  uploadLogo(file) {
    const body = new FormData()
    body.append('file', file)
    return write('uploadLogo', () => apiClient.post('/entities/me/logo', body), {
      file: file.type,
    })
  },
  removeLogo() {
    return write('removeLogo', () => apiClient.delete('/entities/me/logo'), {})
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
