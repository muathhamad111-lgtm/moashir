import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toAssessmentSettings } from './mappers/accredited'

const { read, write } = domainRequests('assessmentSettings', 'settings-assessment')

function mapData(request) {
  return request.then((envelope) => ({ ...envelope, data: toAssessmentSettings(envelope.data) }))
}

export const assessmentSettingsService = {
  get() {
    return mapData(read('get', () => apiClient.get('/settings/assessment')))
  },

  update(payload) {
    return mapData(write('update', () => apiClient.put('/settings/assessment', payload), payload))
  },
}
