import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import {
  toAssignmentRow,
  toCommitteeScores,
  toDiscussionEntry,
  toEvaluation,
  toMemberDiscussion,
  toNotesSaved,
  toSubmission,
} from './mappers/committee'

const { read, write } = domainRequests('committeeWork', 'committee-work')

function assignmentPath(assignmentId) {
  return `/committee/me/assignments/${assignmentId}`
}

function mapData(request, mapper) {
  return request.then((envelope) => ({ ...envelope, data: mapper(envelope.data) }))
}

function mapList(request, mapper) {
  return request.then((envelope) => ({ ...envelope, data: (envelope.data ?? []).map(mapper) }))
}

export const committeeWorkService = {
  listAssignments(params = {}) {
    return mapList(
      read('listAssignments', () => apiClient.get('/committee/me/assignments', { params }), params),
      toAssignmentRow,
    )
  },

  getAssignment(assignmentId) {
    return mapData(
      read('getAssignment', () => apiClient.get(assignmentPath(assignmentId))),
      toEvaluation,
    )
  },

  saveScores(assignmentId, payload) {
    return mapData(
      write(
        'saveScores',
        () => apiClient.put(`${assignmentPath(assignmentId)}/scores`, payload),
        payload,
      ),
      toCommitteeScores,
    )
  },

  saveNotes(assignmentId, payload) {
    return mapData(
      write(
        'saveNotes',
        () => apiClient.put(`${assignmentPath(assignmentId)}/notes`, payload),
        payload,
      ),
      toNotesSaved,
    )
  },

  submit(assignmentId, payload = {}) {
    return mapData(
      write(
        'submit',
        () => apiClient.post(`${assignmentPath(assignmentId)}/submit`, payload),
        payload,
      ),
      toSubmission,
    )
  },

  listDiscussions(params = {}) {
    return mapList(
      read('listDiscussions', () => apiClient.get('/committee/me/discussions', { params }), params),
      toMemberDiscussion,
    )
  },

  respond(discussionId, payload) {
    const request = write(
      'respond',
      () => apiClient.post(`/committee/me/discussions/${discussionId}/entries`, payload),
      payload,
    )
    return mapData(request, toDiscussionEntry)
  },
}
