import { apiClient } from '@/api/client'
import { payloadShape } from '@/utils/payloadShape'
import { serviceRequest } from './serviceRequest'

export const settingsService = {
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
}
