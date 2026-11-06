import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toPathSettings } from './mappers/pathSettings'

const { read, write } = domainRequests('pathSettings', 'settings-assessment-path')

function mapData(request) {
  return request.then((envelope) => ({ ...envelope, data: toPathSettings(envelope.data) }))
}

export const pathSettingsService = {
  get(path) {
    return mapData(read('get', () => apiClient.get(`/settings/assessment/paths/${path}`)))
  },

  update(path, payload) {
    return mapData(
      write('update', () => apiClient.put(`/settings/assessment/paths/${path}`, payload), payload),
    )
  },
}
