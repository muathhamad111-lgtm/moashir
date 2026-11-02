import { apiClient } from '@/api/client'
import { payloadShape } from '@/utils/payloadShape'
import { serviceRequest } from './serviceRequest'

export const committeeService = {
  listCommitteeAssessors(cycleId, params = {}) {
    return serviceRequest(
      'committee.listCommitteeAssessors',
      () => apiClient.get(`/award/committee/${cycleId}/committee-assessors`, { params }),
      { entity: 'committee', paramsShape: payloadShape(params) },
    )
  },

  listAssignments(cycleId, committeeAssessorId) {
    return serviceRequest(
      'committee.listAssignments',
      () => apiClient.get(`/award/committee/${cycleId}/committee-assessors/${committeeAssessorId}/assignments`),
      { entity: 'committee' },
    )
  },

  getCommitteeAssessorScore(cycleId, committeeAssessorId, assessmentId) {
    return serviceRequest(
      'committee.getCommitteeAssessorScore',
      () => apiClient.get(`/award/committee/${cycleId}/committee-assessors/${committeeAssessorId}/score/${assessmentId}`),
      { entity: 'committee' },
    )
  },

  scoreSub(cycleId, committeeAssessorId, assessmentId, payload) {
    return serviceRequest(
      'committee.scoreSub',
      () => apiClient.put(`/award/committee/${cycleId}/committee-assessors/${committeeAssessorId}/score/${assessmentId}/sub`, payload),
      { entity: 'committee', payloadShape: payloadShape(payload) },
    )
  },

  scorePoint(cycleId, committeeAssessorId, assessmentId, payload) {
    return serviceRequest(
      'committee.scorePoint',
      () => apiClient.put(`/award/committee/${cycleId}/committee-assessors/${committeeAssessorId}/score/${assessmentId}/point`, payload),
      { entity: 'committee', payloadShape: payloadShape(payload) },
    )
  },

  saveNotes(cycleId, committeeAssessorId, assessmentId, payload) {
    return serviceRequest(
      'committee.saveNotes',
      () => apiClient.put(`/award/committee/${cycleId}/committee-assessors/${committeeAssessorId}/score/${assessmentId}/notes`, payload),
      { entity: 'committee', payloadShape: payloadShape(payload) },
    )
  },

  complete(cycleId, committeeAssessorId, assessmentId, payload = {}) {
    return serviceRequest(
      'committee.complete',
      () => apiClient.post(`/award/committee/${cycleId}/committee-assessors/${committeeAssessorId}/score/${assessmentId}/complete`, payload),
      { entity: 'committee', payloadShape: payloadShape(payload) },
    )
  },

  disableCommitteeAssessor(cycleId, committeeAssessorId, payload) {
    return serviceRequest(
      'committee.disableCommitteeAssessor',
      () => apiClient.post(`/award/committee/${cycleId}/committee-assessors/${committeeAssessorId}/disable`, payload),
      { entity: 'committee', payloadShape: payloadShape(payload) },
    )
  },

  computeAverage(cycleId, assessmentId) {
    return serviceRequest(
      'committee.computeAverage',
      () => apiClient.post(`/award/committee/${cycleId}/averages/${assessmentId}/compute`),
      { entity: 'committee' },
    )
  },

  listPending(cycleId, params = {}) {
    return serviceRequest(
      'committee.listPending',
      () => apiClient.get(`/award/committee/${cycleId}/secretary/pending`, { params }),
      { entity: 'committee', paramsShape: payloadShape(params) },
    )
  },

  secretaryApprove(cycleId, assessmentId) {
    return serviceRequest(
      'committee.secretaryApprove',
      () => apiClient.post(`/award/committee/${cycleId}/secretary/${assessmentId}/approve`),
      { entity: 'committee' },
    )
  },

  secretaryReturn(cycleId, assessmentId, payload) {
    return serviceRequest(
      'committee.secretaryReturn',
      () => apiClient.post(`/award/committee/${cycleId}/secretary/${assessmentId}/return`, payload),
      { entity: 'committee', payloadShape: payloadShape(payload) },
    )
  },

  getRoster(cycleId) {
    return serviceRequest(
      'committee.getRoster',
      () => apiClient.get(`/award/committee/${cycleId}/roster`),
      { entity: 'committee' },
    )
  },

  addCommitteeAssessor(cycleId, payload) {
    return serviceRequest(
      'committee.addCommitteeAssessor',
      () => apiClient.post(`/award/committee/${cycleId}/roster/committee-assessors`, payload),
      { entity: 'committee', payloadShape: payloadShape(payload) },
    )
  },

  removeCommitteeAssessor(cycleId, committeeAssessorId) {
    return serviceRequest(
      'committee.removeCommitteeAssessor',
      () => apiClient.delete(`/award/committee/${cycleId}/roster/committee-assessors/${committeeAssessorId}`),
      { entity: 'committee' },
    )
  },

  reassignAssessment(cycleId, payload) {
    return serviceRequest(
      'committee.reassignAssessment',
      () => apiClient.post(`/award/committee/${cycleId}/roster/reassign`, payload),
      { entity: 'committee', payloadShape: payloadShape(payload) },
    )
  },

  redistribute(cycleId) {
    return serviceRequest(
      'committee.redistribute',
      () => apiClient.post(`/award/committee/${cycleId}/roster/redistribute`),
      { entity: 'committee' },
    )
  },

  listReviewEntities(cycleId, params = {}) {
    return serviceRequest(
      'committee.listReviewEntities',
      () => apiClient.get(`/award/committee/${cycleId}/secretary/entities`, { params }),
      { entity: 'committee', paramsShape: payloadShape(params) },
    )
  },

  listEntityCommitteeAssessors(cycleId, assessmentId) {
    return serviceRequest(
      'committee.listEntityCommitteeAssessors',
      () => apiClient.get(`/award/committee/${cycleId}/secretary/${assessmentId}/committee-assessors`),
      { entity: 'committee' },
    )
  },

  getEntityCommitteeAssessorDetail(cycleId, assessmentId, committeeAssessorId) {
    return serviceRequest(
      'committee.getEntityCommitteeAssessorDetail',
      () => apiClient.get(`/award/committee/${cycleId}/secretary/${assessmentId}/committee-assessors/${committeeAssessorId}/detail`),
      { entity: 'committee' },
    )
  },
}
