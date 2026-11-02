import { apiClient } from '@/api/client'
import { payloadShape } from '@/utils/payloadShape'
import { serviceRequest } from './serviceRequest'

export const settingsService = {
  getGeneral() {
    return serviceRequest('settings.getGeneral', () => apiClient.get('/settings/general'), {
      entity: 'settings-general',
    })
  },
  updateGeneral(payload) {
    return serviceRequest(
      'settings.updateGeneral',
      () => apiClient.put('/settings/general', payload),
      { entity: 'settings-general', payloadShape: payloadShape(payload) },
    )
  },

  getOtp() {
    return serviceRequest('settings.getOtp', () => apiClient.get('/settings/otp'), {
      entity: 'settings-otp',
    })
  },
  updateOtp(payload) {
    return serviceRequest('settings.updateOtp', () => apiClient.put('/settings/otp', payload), {
      entity: 'settings-otp',
      payloadShape: payloadShape(payload),
    })
  },

  getCertificates() {
    return serviceRequest(
      'settings.getCertificates',
      () => apiClient.get('/settings/certificates'),
      { entity: 'settings-certificates' },
    )
  },
  updateCertificate(kind, payload) {
    return serviceRequest(
      'settings.updateCertificate',
      () => apiClient.put(`/settings/certificates/${kind}`, payload),
      { entity: 'settings-certificates', payloadShape: payloadShape(payload) },
    )
  },

  listKbFiles(params = {}) {
    return serviceRequest(
      'settings.listKbFiles',
      () => apiClient.get('/settings/ai/kb-files', { params }),
      { entity: 'kb-file', paramsShape: payloadShape(params) },
    )
  },
  uploadKbFile(payload) {
    return serviceRequest(
      'settings.uploadKbFile',
      () => apiClient.post('/settings/ai/kb-files', payload),
      { entity: 'kb-file', payloadShape: payloadShape(payload) },
    )
  },
  toggleKbFileApproved(fileId, approved) {
    return serviceRequest(
      'settings.toggleKbFileApproved',
      () => apiClient.patch(`/settings/ai/kb-files/${fileId}/approved`, { approved }),
      { entity: 'kb-file' },
    )
  },
  removeKbFile(fileId) {
    return serviceRequest(
      'settings.removeKbFile',
      () => apiClient.delete(`/settings/ai/kb-files/${fileId}`),
      { entity: 'kb-file' },
    )
  },
  retrainKb() {
    return serviceRequest('settings.retrainKb', () => apiClient.post('/settings/ai/retrain'), {
      entity: 'kb-file',
    })
  },
  listNotificationTypes(params = {}) {
    return serviceRequest(
      'settings.listNotificationTypes',
      () => apiClient.get('/settings/notifications/types', { params }),
      { entity: 'notification-type', paramsShape: payloadShape(params) },
    )
  },
  updateNotificationType(code, payload) {
    return serviceRequest(
      'settings.updateNotificationType',
      () => apiClient.put(`/settings/notifications/types/${code}`, payload),
      { entity: 'notification-type', payloadShape: payloadShape(payload) },
    )
  },
  listNotificationAudit(params = {}) {
    return serviceRequest(
      'settings.listNotificationAudit',
      () => apiClient.get('/settings/notifications/audit', { params }),
      { entity: 'notification-audit', paramsShape: payloadShape(params) },
    )
  },
}
