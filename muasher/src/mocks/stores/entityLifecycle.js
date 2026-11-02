import { ERASURE_KEPT_RECORDS } from '@/constants/entityEnums'
import { entityReject } from './entityRejection'
import { anonymize, isDeleted, referenceOf } from './entitiesStore'
import { runEntityRestoreSteps } from './entityRestoreSteps'

export const VERIFICATION_COOLDOWN_MINUTES = 15

const MINUTE_MS = 60 * 1000

function assertNotFrozen(entity) {
  if (isDeleted(entity)) throw entityReject.deleted()
}

function assertStatus(entity, status) {
  if (entity.status !== status) throw entityReject.illegalTransition()
}

export function deactivate(entity, reason) {
  assertNotFrozen(entity)
  assertStatus(entity, 'active')
  entity.status = 'deactivated'
  entity.deactivatedAt = new Date().toISOString()
  entity.deactivationReason = reason
  return entity
}

export function reactivate(entity) {
  assertNotFrozen(entity)
  assertStatus(entity, 'deactivated')
  entity.status = 'active'
  entity.deactivatedAt = null
  entity.deactivationReason = null
  return entity
}

function cooldownSecondsLeft(remindedAt, now) {
  if (!remindedAt) return 0
  const elapsed = now - new Date(remindedAt).getTime()
  return Math.ceil((VERIFICATION_COOLDOWN_MINUTES * MINUTE_MS - elapsed) / 1000)
}

export function resendVerification(entity, now = Date.now()) {
  assertNotFrozen(entity)
  assertStatus(entity, 'pending-verification')
  const secondsLeft = cooldownSecondsLeft(entity.verificationRemindedAt, now)
  if (secondsLeft > 0) throw entityReject.verificationCooldown(secondsLeft)
  entity.verificationRemindedAt = new Date(now).toISOString()
  return entity
}

export function deletionImpactOf(entity) {
  const openRequests = entity.openRequests.map((request) => ({ ...request }))
  if (entity.incomingOpenRequest) {
    entity.openRequests.push(entity.incomingOpenRequest)
    entity.incomingOpenRequest = null
  }
  return openRequests
}

function sameNumbers(left, right) {
  const sorted = (list) => [...new Set(list)].sort()
  return JSON.stringify(sorted(left)) === JSON.stringify(sorted(right))
}

function acknowledges(instruction, openNumbers) {
  if (!openNumbers.length) return true
  return instruction.confirmOpenRequests === true && sameNumbers(instruction.acknowledgedRequestNumbers ?? [], openNumbers)
}

export function softDelete(entity, instruction) {
  if (isDeleted(entity)) throw entityReject.illegalTransition()
  const openNumbers = entity.openRequests.map((request) => request.number)
  if (!acknowledges(instruction, openNumbers)) {
    throw entityReject.openRequestsConfirmationRequired(entity.openRequests.map((request) => ({ ...request, cycleId: null, isFrozen: false })))
  }
  entity.deletedAt = new Date().toISOString()
  entity.deletionReason = instruction.reason
  return { entity, frozenRequestNumbers: openNumbers }
}

export function restore(entity) {
  if (!isDeleted(entity)) throw entityReject.illegalTransition()
  entity.deletedAt = null
  entity.deletionReason = null
  entity.restoredAt = new Date().toISOString()
  runEntityRestoreSteps(entity)
  return entity
}

function referenceMatches(entity, confirmation) {
  return String(confirmation ?? '').trim().toUpperCase() === referenceOf(entity)
}

export function permanentlyDelete(entity, { confirmation, disclaimerAcknowledged }) {
  if (!isDeleted(entity)) throw entityReject.notSoftDeleted()
  if (disclaimerAcknowledged !== true) throw entityReject.disclaimerRequired()
  if (!referenceMatches(entity, confirmation)) throw entityReject.confirmationMismatch()
  const reference = referenceOf(entity)
  const pseudonym = anonymize(entity)
  return { reference, pseudonym, erasedAt: entity.anonymizedAt, kept: [...ERASURE_KEPT_RECORDS] }
}
