import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toAssessment } from './mappers/assessments'
import { toAccreditedForm, toCvSaved, toPassedStations } from './mappers/accredited'

const { read, write } = domainRequests('submission', 'assessment')

function mapData(request, mapper) {
  return request.then((envelope) => ({ ...envelope, data: mapper(envelope.data) }))
}

export const submissionService = {
  getForm(assessmentId) {
    return mapData(
      read('getForm', () => apiClient.get(`/assessments/${assessmentId}/form`)),
      toAccreditedForm,
    )
  },

  saveCv(assessmentId, payload) {
    return mapData(
      write('saveCv', () => apiClient.put(`/assessments/${assessmentId}/cv`, payload), payload),
      toCvSaved,
    )
  },

  passStation(assessmentId, mainId) {
    return mapData(
      write('passStation', () =>
        apiClient.put(`/assessments/${assessmentId}/stations/${mainId}/passed`),
      ),
      (data) => toPassedStations(data?.passedStations),
    )
  },

  submit(assessmentId, payload = {}) {
    const request = write(
      'submit',
      () => apiClient.post(`/assessments/${assessmentId}/submit`, payload),
      payload,
    )
    return mapData(request, toAssessment)
  },
}
