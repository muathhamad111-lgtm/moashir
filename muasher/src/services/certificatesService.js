import { payloadShape } from '@/utils/payloadShape'
import { apiClient } from '@/api/client'
import { serviceRequest } from './serviceRequest'

export const certificatesService = {
  list(params = {}) {
    return serviceRequest('certificates.list', () => apiClient.get('/certificates', { params }), {
      entity: 'certificate',
      paramsShape: payloadShape(params),
    })
  },

  get(certificateId) {
    return serviceRequest(
      'certificates.get',
      () => apiClient.get(`/certificates/${certificateId}`),
      { entity: 'certificate' },
    )
  },

  download(certificateId) {
    return serviceRequest(
      'certificates.download',
      () => apiClient.post(`/certificates/${certificateId}/download`),
      { entity: 'certificate' },
    )
  },
}
