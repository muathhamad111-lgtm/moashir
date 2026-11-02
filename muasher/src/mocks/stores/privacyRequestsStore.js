import { PRIVACY_REQUEST_STATUSES as S } from '@/constants/entityEnums'
import { privacyRequestsFixture } from '../fixtures/entities'
import { entityReject } from './entityRejection'

let requests = []
let nextRequestId = 1

export function resetPrivacyRequests() {
  requests = privacyRequestsFixture.map((request) => ({ ...request }))
  nextRequestId = Math.max(...requests.map((request) => request.id)) + 1
}

resetPrivacyRequests()

export function findPrivacyRequest(id) {
  return requests.find((request) => request.id === Number(id)) ?? null
}

export function listPrivacyRequests({ status, kind, entityId }) {
  return requests
    .filter((request) => !status || request.status === status)
    .filter((request) => !kind || request.kind === kind)
    .filter((request) => !entityId || request.entityId === Number(entityId))
    .sort((left, right) => right.createdAt.localeCompare(left.createdAt))
}

export function createPrivacyRequest(actor, { entityId, kind, details }) {
  const request = {
    id: nextRequestId++,
    entityId: Number(entityId),
    kind,
    status: S.OPEN,
    details,
    createdBy: { id: actor.id, name: actor.name },
    decidedBy: null,
    decisionNote: null,
    decidedAt: null,
    createdAt: new Date().toISOString(),
  }
  requests.push(request)
  return request
}

export function decidePrivacyRequest(request, actor, status, note) {
  if (request.status !== S.OPEN) throw entityReject.illegalTransition()
  request.status = status
  request.decidedBy = { id: actor.id, name: actor.name }
  request.decisionNote = note
  request.decidedAt = new Date().toISOString()
  return request
}

export function deletePrivacyRequestsOf(entityId) {
  requests = requests.filter((request) => request.entityId !== entityId)
}
