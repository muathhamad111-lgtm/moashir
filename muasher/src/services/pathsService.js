import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toAssessment, toAssessmentRecord } from './mappers/assessments'
import { toPathRowFields } from './mappers/paths'
import { API_PATHS } from '@/constants/entityPaths'

const { read } = domainRequests('paths', 'assessment')
const NOT_FOUND = 404

export function toPathRequest(raw = {}) {
  return { ...toAssessment(raw), ...toPathRowFields(raw) }
}

function toPathRecord(raw = {}) {
  return { ...toAssessmentRecord(raw), ...toPathRowFields(raw) }
}

export const pathsService = {
  listRequests(path, { page = 1, perPage } = {}) {
    const params = {
      'filter[path]': API_PATHS[path] ?? path,
      page,
      ...(perPage ? { perPage } : {}),
    }
    return read('listRequests', () => apiClient.get('/assessments', { params }), params).then(
      (envelope) => ({ ...envelope, data: (envelope.data ?? []).map(toPathRequest) }),
    )
  },

  getRequest(assessmentId) {
    return read('getRequest', () => apiClient.get(`/assessments/${assessmentId}`), null, [
      NOT_FOUND,
    ]).then((envelope) => ({ ...envelope, data: toPathRecord(envelope.data) }))
  },
}
