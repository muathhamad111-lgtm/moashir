import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { sessionCan } from '../sessionPermissions'
import { assessmentReject } from '../assessments/assessmentRejection'
import { ACCESS, accessOf } from '../assessments/assessmentAccess'
import { isInFiltering } from './advancedStates'
import { isLinkedAssessor, isMember } from './cycleStore'

export const AWARD_PERMISSIONS = {
  CYCLES_VIEW: 'award.cycles.view',
  CYCLES_MANAGE: 'award.cycles.manage',
  FILTERING_VIEW: 'award.filtering.view',
  FILTERING_DECIDE: 'award.filtering.decide',
  COMPLETION_RETURN: 'requests.completion.return',
  ADVANCED_MANAGE: 'assessment.advanced.manage',
}

const GLOBAL_READERS = ['system_admin', 'request_follower']
const SYSTEM_ADMIN = 'system_admin'

function holds(user, roles) {
  return roles.some((role) => user.roles.includes(role))
}

export function readsEveryCycle(user) {
  return user.superAdmin || (sessionCan(AWARD_PERMISSIONS.CYCLES_VIEW) && holds(user, GLOBAL_READERS))
}

export function managesEveryCycle(user) {
  return user.superAdmin || (sessionCan(AWARD_PERMISSIONS.CYCLES_MANAGE) && holds(user, [SYSTEM_ADMIN]))
}

export function canView(user, cycle) {
  if (readsEveryCycle(user)) return true
  return sessionCan(AWARD_PERMISSIONS.CYCLES_VIEW) && (isMember(cycle, user.id) || isLinkedAssessor(cycle, user.id))
}

export function canManage(user, cycle) {
  if (managesEveryCycle(user)) return true
  return sessionCan(AWARD_PERMISSIONS.CYCLES_MANAGE) && isMember(cycle, user.id, ['award_secretary'])
}

export function revealsStaff(user, cycle) {
  return canManage(user, cycle) || readsEveryCycle(user)
}

function canViewFiltering(user, cycle) {
  if (user.superAdmin) return true
  if (!sessionCan(AWARD_PERMISSIONS.FILTERING_VIEW)) return false
  return holds(user, [SYSTEM_ADMIN]) || isMember(cycle, user.id)
}

export function canDecideFiltering(user, cycle) {
  return user.superAdmin || (sessionCan(AWARD_PERMISSIONS.FILTERING_DECIDE) && isMember(cycle, user.id, ['filtering_officer']))
}

function denyOrHide(user, cycle) {
  return canView(user, cycle) ? assessmentReject.forbidden() : assessmentReject.notFound()
}

function requireCycle(cycle) {
  if (!cycle) throw assessmentReject.notFound()
}

export function authorizeViewAnyCycles(user) {
  if (!user.superAdmin && !sessionCan(AWARD_PERMISSIONS.CYCLES_VIEW)) throw assessmentReject.forbidden()
}

export function authorizeCreateCycle(user) {
  if (!user.superAdmin && !sessionCan(AWARD_PERMISSIONS.CYCLES_MANAGE)) throw assessmentReject.forbidden()
}

export function authorizeViewCycle(user, cycle) {
  requireCycle(cycle)
  if (!user.superAdmin && !sessionCan(AWARD_PERMISSIONS.CYCLES_VIEW)) throw assessmentReject.forbidden()
  if (!canView(user, cycle)) throw assessmentReject.notFound()
}

export function authorizeManageCycle(user, cycle) {
  requireCycle(cycle)
  if (!canManage(user, cycle)) throw denyOrHide(user, cycle)
}

export function authorizeReopenCycle(user, cycle) {
  requireCycle(cycle)
  if (!user.superAdmin) throw denyOrHide(user, cycle)
}

export function authorizeViewFiltering(user, cycle) {
  requireCycle(cycle)
  if (canViewFiltering(user, cycle)) return
  if (!sessionCan(AWARD_PERMISSIONS.FILTERING_VIEW)) throw assessmentReject.forbidden()
  throw denyOrHide(user, cycle)
}

function requireFilteringRequest(cycle, record) {
  const belongs = record && record.path === ASSESSMENT_PATHS.ADVANCED && record.awardCycleId === cycle.id && isInFiltering(record.status)
  if (!belongs) throw assessmentReject.notFound()
}

export function authorizeFilteringRequest(user, cycle, record) {
  requireCycle(cycle)
  requireFilteringRequest(cycle, record)
  authorizeViewFiltering(user, cycle)
}

export function authorizeDecideFiltering(user, cycle, record) {
  requireCycle(cycle)
  requireFilteringRequest(cycle, record)
  if (canDecideFiltering(user, cycle)) return
  authorizeViewFiltering(user, cycle)
  throw assessmentReject.forbidden()
}

export function authorizeAdvancedEntity(user) {
  if (user.entityId === null || !sessionCan(AWARD_PERMISSIONS.ADVANCED_MANAGE)) throw assessmentReject.forbidden()
}

export function authorizeFollowAdvanced(user, record) {
  if (record.path !== ASSESSMENT_PATHS.ADVANCED) throw assessmentReject.notFound()
  if (user.superAdmin || sessionCan(AWARD_PERMISSIONS.COMPLETION_RETURN)) return
  throw accessOf(user, record) === ACCESS.NONE ? assessmentReject.notFound() : assessmentReject.forbidden()
}

export function authorizeDeleteDraft(user, record) {
  if (record.entityId !== Number(user.entityId)) throw accessOf(user, record) === ACCESS.NONE ? assessmentReject.notFound() : assessmentReject.forbidden()
  if (record.path !== ASSESSMENT_PATHS.ADVANCED || !sessionCan(AWARD_PERMISSIONS.ADVANCED_MANAGE)) throw assessmentReject.forbidden()
}
