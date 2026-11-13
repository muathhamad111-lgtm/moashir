import { sessionCan } from '../sessionPermissions'
import { assessmentReject } from '../assessments/assessmentRejection'
import { isMember } from '../award/cycleStore'
import { canView } from '../award/cycleAccess'

export const COMMITTEE_PERMISSIONS = {
  MANAGE: 'award.committee.manage',
  ASSIGNED_VIEW: 'award.committee.assigned.view',
  SCORE: 'award.committee.score',
  SECRETARY_REVIEW: 'award.secretary.review',
  SECRETARY_DECIDE: 'award.secretary.decide',
  RESULTS_VIEW: 'award.results.view',
  RESULTS_RANK: 'award.results.rank',
  RESULTS_APPROVE: 'award.results.approve',
  RESULTS_ANNOUNCE: 'award.results.announce',
}

const P = COMMITTEE_PERMISSIONS
const GLOBAL_MANAGERS = ['system_admin', 'request_follower']
const SYSTEM_ADMIN = 'system_admin'
const SECRETARY = ['award_secretary']

function holds(user, roles) {
  return roles.some((role) => user.roles.includes(role))
}

function isSecretaryOf(user, cycle) {
  return isMember(cycle, user.id, SECRETARY)
}

export function managesRoster(user, cycle) {
  if (user.superAdmin) return true
  return sessionCan(P.MANAGE) && (holds(user, GLOBAL_MANAGERS) || isMember(cycle, user.id))
}

function readsSecretaryDesk(user, cycle) {
  if (user.superAdmin) return true
  return (
    sessionCan(P.SECRETARY_REVIEW) && (holds(user, [SYSTEM_ADMIN]) || isSecretaryOf(user, cycle))
  )
}

export function decidesSecretaryDesk(user, cycle) {
  return user.superAdmin || (sessionCan(P.SECRETARY_DECIDE) && isSecretaryOf(user, cycle))
}

function readsResults(user, cycle) {
  if (user.superAdmin) return true
  return sessionCan(P.RESULTS_VIEW) && (holds(user, [SYSTEM_ADMIN]) || isSecretaryOf(user, cycle))
}

function denyOrHide(user, cycle) {
  return canView(user, cycle) ? assessmentReject.forbidden() : assessmentReject.notFound()
}

function guard(allowed, user, cycle, permission) {
  if (allowed) return
  if (!user.superAdmin && permission && !sessionCan(permission)) throw assessmentReject.forbidden()
  throw denyOrHide(user, cycle)
}

export function authorizeViewRoster(user, cycle) {
  if (!user.superAdmin && !sessionCan(P.MANAGE) && !sessionCan(P.SECRETARY_REVIEW))
    throw assessmentReject.forbidden()
  guard(managesRoster(user, cycle) || readsSecretaryDesk(user, cycle), user, cycle, null)
}

export function authorizeManageRoster(user, cycle) {
  guard(managesRoster(user, cycle), user, cycle, P.MANAGE)
}

export function authorizeViewSecretary(user, cycle) {
  guard(readsSecretaryDesk(user, cycle), user, cycle, P.SECRETARY_REVIEW)
}

export function authorizeDecideSecretary(user, cycle) {
  if (decidesSecretaryDesk(user, cycle)) return
  authorizeViewSecretary(user, cycle)
  throw assessmentReject.forbidden()
}

export function authorizeViewResults(user, cycle) {
  guard(readsResults(user, cycle), user, cycle, P.RESULTS_VIEW)
}

function authorizeResultsWrite(user, cycle, permission) {
  if (user.superAdmin || (sessionCan(permission) && isSecretaryOf(user, cycle))) return
  authorizeViewResults(user, cycle)
  throw assessmentReject.forbidden()
}

export function authorizeRank(user, cycle) {
  authorizeResultsWrite(user, cycle, P.RESULTS_RANK)
}

export function authorizeApproveResults(user, cycle) {
  authorizeResultsWrite(user, cycle, P.RESULTS_APPROVE)
}

export function authorizeAnnounce(user, cycle) {
  authorizeResultsWrite(user, cycle, P.RESULTS_ANNOUNCE)
}

export function authorizeUnapprove(user, cycle) {
  if (user.superAdmin) return
  guard(readsResults(user, cycle), user, cycle, null)
  throw assessmentReject.forbidden()
}

export function authorizeOwnWork(user) {
  if (!user.superAdmin && !sessionCan(P.ASSIGNED_VIEW)) throw assessmentReject.forbidden()
}

export function requireReason(user, reason) {
  if (user.superAdmin && !reason) throw assessmentReject.reasonRequired()
}
