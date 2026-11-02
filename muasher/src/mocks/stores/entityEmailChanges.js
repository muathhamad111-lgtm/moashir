import { EMAIL_CHANGE_POLICIES, EMAIL_CHANGE_STATUSES as S } from '@/constants/entityEnums'
import { emailChangesFixture } from '../fixtures/entities'
import { entityReject } from './entityRejection'
import { findEntity, isDeleted, ownerEmailTaken, setOwnerEmail } from './entitiesStore'

let changes = []
let nextChangeId = 1
let policy = EMAIL_CHANGE_POLICIES.REQUIRES_APPROVAL

export function resetEmailChanges() {
  changes = emailChangesFixture.map((change) => ({ ...change, challengeId: null }))
  nextChangeId = Math.max(...changes.map((change) => change.id)) + 1
  policy = EMAIL_CHANGE_POLICIES.REQUIRES_APPROVAL
}

resetEmailChanges()

export function setEmailChangePolicy(next) {
  policy = next
}

export function findEmailChange(id) {
  return changes.find((change) => change.id === Number(id)) ?? null
}

export function awaitingChangeOf(entityId) {
  return changes.find((change) => change.entityId === entityId && change.status === S.AWAITING_APPROVAL) ?? null
}

export function listEmailChanges(status) {
  const matching = status === 'all' ? changes : changes.filter((change) => change.status === status)
  return [...matching].sort((left, right) => right.requestedAt.localeCompare(left.requestedAt))
}

function cancelUnconfirmed(entityId) {
  changes
    .filter((change) => change.entityId === entityId && change.status === S.AWAITING_OTP)
    .forEach((change) => {
      change.status = S.CANCELLED
    })
}

export function requestChange(entity, newEmail, challengeId) {
  if (entity.status !== 'active') throw entityReject.illegalTransition()
  if (awaitingChangeOf(entity.id)) throw entityReject.emailPendingApproval()
  cancelUnconfirmed(entity.id)
  const change = {
    id: nextChangeId++,
    entityId: entity.id,
    oldEmail: entity.owner.email,
    newEmail,
    policy,
    status: S.AWAITING_OTP,
    requestedAt: new Date().toISOString(),
    otpVerifiedAt: null,
    appliedAt: null,
    decisionReason: null,
    challengeId,
  }
  changes.push(change)
  return change
}

function apply(change) {
  const entity = findEntity(change.entityId)
  if (ownerEmailTaken(change.newEmail)) throw entityReject.emailAddressTaken()
  setOwnerEmail(entity, change.newEmail)
  change.status = S.APPLIED
  change.appliedAt = new Date().toISOString()
}

export function confirmChange(entity, challengeId) {
  const change = changes.find(
    (item) => item.entityId === entity.id && item.challengeId === challengeId && item.status === S.AWAITING_OTP,
  )
  if (!change) throw entityReject.otpInvalid()
  change.otpVerifiedAt = new Date().toISOString()
  if (change.policy === EMAIL_CHANGE_POLICIES.IMMEDIATE_NOTIFY) apply(change)
  else change.status = S.AWAITING_APPROVAL
  return change
}

function assertAwaiting(change) {
  if (change.status !== S.AWAITING_APPROVAL) throw entityReject.illegalTransition()
}

export function approveChange(change) {
  const entity = findEntity(change.entityId)
  if (entity && isDeleted(entity)) throw entityReject.deleted()
  assertAwaiting(change)
  apply(change)
  return change
}

export function rejectChange(change, reason) {
  assertAwaiting(change)
  change.status = S.REJECTED
  change.decisionReason = reason
  return change
}
