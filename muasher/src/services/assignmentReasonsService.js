import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const { read, write } = domainRequests('assignmentReasons', 'settings-assignment-reasons')
const ENDPOINT = '/settings/assessment/assignment-reasons'

export function toAssignmentReasonRules(raw = {}) {
  return {
    assignRequired: raw.assignRequired === true,
    reassignRequired: raw.reassignRequired !== false,
  }
}

function mapData(request) {
  return request.then((envelope) => ({
    ...envelope,
    data: toAssignmentReasonRules(envelope.data),
  }))
}

export const assignmentReasonsService = {
  get() {
    return mapData(read('get', () => apiClient.get(ENDPOINT)))
  },

  update(payload) {
    return mapData(write('update', () => apiClient.put(ENDPOINT, payload), payload))
  },
}
