import { ROLES } from '@/constants/roles'
import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { currentSessionUser } from '../sessionStore'
import { sessionCan } from '../sessionPermissions'
import { assessmentReject } from './assessmentRejection'
import { findCycle, isMember } from '../award/cycleStore'

export const ASSESSMENT_PERMISSIONS = {
  HISTORY_VIEW: 'assessment.history.view',
  SELF_MANAGE: 'assessment.self.manage',
  ASSIGNED_VIEW: 'requests.assigned.view',
}

export const ACCESS = { OWNER: 'owner', ASSIGNEE: 'assignee', READ_ONLY: 'read_only', NONE: 'none' }

const REVIEWING_ASSIGNMENTS = ['active', 'completed']

const GLOBAL_READER_ROLES = [ROLES.SYSTEM_ADMIN, ROLES.REQUEST_FOLLOWER]

export function signedInActor() {
  const user = currentSessionUser()
  if (!user) throw assessmentReject.unauthenticated()
  return user
}

export function readsEveryAssessment(user) {
  if (user.superAdmin) return true
  if (!sessionCan(ASSESSMENT_PERMISSIONS.HISTORY_VIEW)) return false
  return GLOBAL_READER_ROLES.some((role) => user.roles.includes(role))
}

export function isOwner(user, record) {
  return user.entityId !== null && record.entityId === Number(user.entityId)
}

export function isReviewedBy(user, record) {
  if (!sessionCan(ASSESSMENT_PERMISSIONS.ASSIGNED_VIEW)) return false
  return (record.assignments ?? []).some(
    (assignment) =>
      assignment.assessorId === user.id && REVIEWING_ASSIGNMENTS.includes(assignment.status),
  )
}

export function isCycleMemberOf(user, record) {
  if (record.path !== ASSESSMENT_PATHS.ADVANCED) return false
  const cycle = findCycle(record.awardCycleId)
  return Boolean(cycle) && isMember(cycle, user.id)
}

export function accessOf(user, record) {
  if (isOwner(user, record)) return ACCESS.OWNER
  if (readsEveryAssessment(user)) return ACCESS.READ_ONLY
  if (isReviewedBy(user, record)) return ACCESS.ASSIGNEE
  return isCycleMemberOf(user, record) && sessionCan(ASSESSMENT_PERMISSIONS.HISTORY_VIEW)
    ? ACCESS.READ_ONLY
    : ACCESS.NONE
}

export function inRecordScope(user, record) {
  return accessOf(user, record) !== ACCESS.NONE
}

export function authorizeViewAny(user) {
  if (user.superAdmin) return
  if (!sessionCan(ASSESSMENT_PERMISSIONS.HISTORY_VIEW)) throw assessmentReject.forbidden()
}

export function authorizeView(user, record) {
  if (user.superAdmin) return
  if (!sessionCan(ASSESSMENT_PERMISSIONS.HISTORY_VIEW)) throw assessmentReject.forbidden()
  if (!inRecordScope(user, record)) throw assessmentReject.notFound()
}

export function authorizeCreateSelf(user) {
  if (user.superAdmin) return
  if (user.entityId === null || !sessionCan(ASSESSMENT_PERMISSIONS.SELF_MANAGE))
    throw assessmentReject.forbidden()
}

export function authorizeManageSelf(user, record) {
  if (user.superAdmin) return
  if (!isOwner(user, record)) {
    throw readsEveryAssessment(user) ? assessmentReject.forbidden() : assessmentReject.notFound()
  }
  if (record.path !== ASSESSMENT_PATHS.SELF || !sessionCan(ASSESSMENT_PERMISSIONS.SELF_MANAGE)) {
    throw assessmentReject.forbidden()
  }
}
