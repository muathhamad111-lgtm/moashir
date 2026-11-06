import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const { read, write } = domainRequests('content.maturityTexts', 'maturity-texts')

export const maturityTextsService = {
  list(params = {}) {
    return read('list', () => apiClient.get('/content/maturity-texts', { params }), params)
  },

  save(band, payload) {
    return write(
      'save',
      () => apiClient.put(`/content/maturity-texts/${band}`, payload),
      payload,
      [404],
    )
  },

  remove(id) {
    return write('remove', () => apiClient.delete(`/content/maturity-texts/${id}`), null, [404])
  },
}
