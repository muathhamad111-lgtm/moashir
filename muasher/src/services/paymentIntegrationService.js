import { apiClient } from '@/api/client'
import { integrationRequests } from './integrationRequest'

const PATH = '/settings/payment'
const ASSOCIATION_FILE_PATH = `${PATH}/apple-pay/association-file`

const { read, write } = integrationRequests('paymentIntegration', 'payment-integration')

export const paymentIntegrationService = {
  get() {
    return read('get', () => apiClient.get(PATH))
  },
  update(payload) {
    return write('update', () => apiClient.put(PATH, payload), payload)
  },
  testConnection(mode) {
    const payload = { mode }
    return write(
      'testConnection',
      () => apiClient.post(`${PATH}/test-connection`, payload),
      payload,
    )
  },
  registerWebhook(mode, reason = null) {
    const payload = reason ? { mode, reason } : { mode }
    return write(
      'registerWebhook',
      () => apiClient.post(`${PATH}/webhook/register`, payload),
      payload,
    )
  },
  uploadAssociationFile(file) {
    const form = new FormData()
    form.append('file', file)
    return write('uploadAssociationFile', () => apiClient.post(ASSOCIATION_FILE_PATH, form), {
      file: 'file',
    })
  },
  removeAssociationFile() {
    return write('removeAssociationFile', () => apiClient.delete(ASSOCIATION_FILE_PATH))
  },
  checkAssociationFile() {
    return write('checkAssociationFile', () => apiClient.post(`${ASSOCIATION_FILE_PATH}/check`))
  },
}
