import { apiClient } from '@/api/client'
import { integrationRequests } from './integrationRequest'

const PATH = '/settings/sms'

const { read, write } = integrationRequests('smsIntegration', 'sms-integration')

export const smsIntegrationService = {
  get() {
    return read('get', () => apiClient.get(PATH))
  },
  update(payload) {
    return write('update', () => apiClient.put(PATH, payload), payload)
  },
  testConnection(provider) {
    return write('testConnection', () => apiClient.post(`${PATH}/${provider}/test-connection`))
  },
  listTaqnyatSenders() {
    return write('listTaqnyatSenders', () => apiClient.get(`${PATH}/taqnyat/senders`))
  },
  listMsegatTemplates() {
    return write('listMsegatTemplates', () => apiClient.get(`${PATH}/msegat/templates`))
  },
  sendTest(payload) {
    return write('sendTest', () => apiClient.post(`${PATH}/test`, payload), payload)
  },
}
