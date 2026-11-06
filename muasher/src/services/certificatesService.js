import { payloadShape } from '@/utils/payloadShape'
import { apiClient } from '@/api/client'
import { serviceRequest } from './serviceRequest'
import { toCertificateCard, toStaffCertificate, toVerification } from './mappers/certificates'

const DOWNLOAD_REJECTIONS = [409, 503]
const VERIFY_REJECTIONS = [429]
const STAFF_REJECTIONS = [404, 409, 422, 429]
const REQUEST_CERTIFICATES_LIMIT = 20

function staffWrite(action, execute, payload) {
  return serviceRequest(
    `certificates.${action}`,
    execute,
    { entity: 'certificate', payloadShape: payloadShape(payload) },
    { expectedStatuses: STAFF_REJECTIONS },
  ).then((envelope) => ({ ...envelope, data: toStaffCertificate(envelope.data) }))
}

export const certificatesService = {
  list(params = {}) {
    return serviceRequest('certificates.list', () => apiClient.get('/certificates', { params }), {
      entity: 'certificate',
      paramsShape: payloadShape(params),
    })
  },

  listCards(params = {}) {
    return certificatesService.list(params).then((envelope) => ({
      ...envelope,
      data: (envelope.data ?? []).map(toCertificateCard),
    }))
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
      { expectedStatuses: DOWNLOAD_REJECTIONS },
    )
  },

  listForRequest(assessmentId) {
    const params = { 'filter[assessmentId]': assessmentId, perPage: REQUEST_CERTIFICATES_LIMIT }
    return certificatesService.list(params).then((envelope) => ({
      ...envelope,
      data: (envelope.data ?? []).map(toStaffCertificate),
    }))
  },

  revoke(certificateId, reason) {
    const payload = { reason }
    return staffWrite(
      'revoke',
      () => apiClient.post(`/certificates/${certificateId}/revoke`, payload),
      payload,
    )
  },

  reissue(certificateId, reason) {
    const payload = { reason }
    return staffWrite(
      'reissue',
      () => apiClient.post(`/certificates/${certificateId}/reissue`, payload),
      payload,
    )
  },

  issueForRequest(assessmentId, payload = {}) {
    return staffWrite(
      'issueForRequest',
      () => apiClient.post(`/assessments/${assessmentId}/certificate`, payload),
      payload,
    )
  },

  verify(code) {
    return serviceRequest(
      'certificates.verify',
      () => apiClient.get(`/verify/${encodeURIComponent(code)}`),
      { entity: 'certificate-verification' },
      { expectedStatuses: VERIFY_REJECTIONS },
    ).then((envelope) => ({ ...envelope, data: toVerification(envelope.data) }))
  },
}
