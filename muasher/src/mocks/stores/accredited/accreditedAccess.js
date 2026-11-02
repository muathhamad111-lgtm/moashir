import { sessionCan } from '../sessionPermissions'
import { assessmentReject } from '../assessments/assessmentRejection'
import { ACCESS, accessOf, isOwner } from '../assessments/assessmentAccess'
import { activeAssignment } from './accreditedRecords'
import { isReviewedPath, pathRules } from '../assessments/reviewedPaths'

export const ACCREDITED_PERMISSIONS = {
  ACCREDITED_MANAGE: 'assessment.accredited.manage',
  EVIDENCE_MANAGE: 'evidence.manage',
  EVIDENCE_VIEW: 'evidence.view',
  QUEUE_VIEW: 'requests.queue.view',
  ASSIGNED_VIEW: 'requests.assigned.view',
  ASSIGN: 'requests.assign',
  REVIEW_READ: 'requests.review.read',
  SCORE_MANAGE: 'assessment.score.manage',
  CONTEXT_VIEW: 'assessment.context.view',
  SETTINGS_MANAGE: 'settings.assessment.manage',
}

function denyFor(user, record) {
  return accessOf(user, record) === ACCESS.NONE ? assessmentReject.notFound() : assessmentReject.forbidden()
}

function requireReviewed(record) {
  if (!isReviewedPath(record.path)) throw assessmentReject.notFound()
}

function permissionFor(record, permission) {
  return permission === ACCREDITED_PERMISSIONS.ACCREDITED_MANAGE ? pathRules(record.path).permission : permission
}

function isActiveAssignee(user, record) {
  return activeAssignment(record)?.assessorId === user.id
}

export function authorizeAccreditedCurrent(user) {
  if (user.superAdmin) return
  if (user.entityId === null || !sessionCan(ACCREDITED_PERMISSIONS.ACCREDITED_MANAGE)) throw assessmentReject.forbidden()
}

export function authorizeManageOwn(user, record, permission) {
  if (user.superAdmin) return
  if (!isOwner(user, record)) throw denyFor(user, record)
  if (!isReviewedPath(record.path) || !sessionCan(permissionFor(record, permission))) throw assessmentReject.forbidden()
}

export function authorizeViewEvidence(user, record) {
  requireReviewed(record)
  if (user.superAdmin) return
  if (!sessionCan(ACCREDITED_PERMISSIONS.EVIDENCE_VIEW)) throw assessmentReject.forbidden()
  if (accessOf(user, record) === ACCESS.NONE) throw assessmentReject.notFound()
}

export function authorizeRequestBundle(user, record) {
  authorizeViewEvidence(user, record)
  if (!user.superAdmin && accessOf(user, record) === ACCESS.OWNER) throw assessmentReject.forbidden()
}

export function authorizeViewBundle(user, record, bundle) {
  if (!bundle || (!user.superAdmin && bundle.requesterId !== user.id)) throw assessmentReject.notFound()
  authorizeRequestBundle(user, record)
}

export function authorizeQueue(user) {
  if (!user.superAdmin && !sessionCan(ACCREDITED_PERMISSIONS.QUEUE_VIEW)) throw assessmentReject.forbidden()
}

export function authorizeAssignedList(user) {
  if (!user.superAdmin && !sessionCan(ACCREDITED_PERMISSIONS.ASSIGNED_VIEW)) throw assessmentReject.forbidden()
}

export function authorizeViewRequest(user, record) {
  requireReviewed(record)
  if (user.superAdmin || sessionCan(ACCREDITED_PERMISSIONS.QUEUE_VIEW)) return
  if (!sessionCan(ACCREDITED_PERMISSIONS.ASSIGNED_VIEW)) throw assessmentReject.forbidden()
  if (accessOf(user, record) !== ACCESS.ASSIGNEE) throw assessmentReject.notFound()
}

export function authorizeListAssessors(user) {
  if (!user.superAdmin && !sessionCan(ACCREDITED_PERMISSIONS.ASSIGN)) throw assessmentReject.forbidden()
}

export function authorizeAssign(user, record) {
  requireReviewed(record)
  authorizeListAssessors(user)
}

export function authorizeReview(user, record) {
  requireReviewed(record)
  if (user.superAdmin) return
  const access = accessOf(user, record)
  if (access === ACCESS.ASSIGNEE && sessionCan(ACCREDITED_PERMISSIONS.SCORE_MANAGE)) return
  if (access === ACCESS.READ_ONLY && sessionCan(ACCREDITED_PERMISSIONS.REVIEW_READ)) return
  throw denyFor(user, record)
}

export function authorizeScore(user, record) {
  requireReviewed(record)
  if (user.superAdmin) return
  if (sessionCan(ACCREDITED_PERMISSIONS.SCORE_MANAGE) && isActiveAssignee(user, record)) return
  throw denyFor(user, record)
}

export function authorizeOverride(user, record) {
  if (user.superAdmin) return
  throw denyFor(user, record)
}

export function authorizeSettings(user) {
  if (!user.superAdmin && !sessionCan(ACCREDITED_PERMISSIONS.SETTINGS_MANAGE)) throw assessmentReject.forbidden()
}
