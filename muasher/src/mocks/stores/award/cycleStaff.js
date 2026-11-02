import { usersFixture } from '../../fixtures/users'
import { grantedPermissionKeys } from '../permissionsStore'
import { assessmentReject, fieldMessage } from '../assessments/assessmentRejection'
import { nowIso } from '../accredited/accreditedRecords'
import { isPositiveInteger, reasonError, throwIfErrors, trimmed } from '../accredited/accreditedValidation'
import { isInitialAssessor, isMember } from './cycleStore'

const ROLE_PERMISSIONS = {
  initial_assessor: 'assessment.score.manage',
  filtering_officer: 'award.filtering.decide',
  award_secretary: 'award.secretary.decide',
}
const MEMBER_ROLES = ['filtering_officer', 'award_secretary']
const SEARCH_MAX = 100
const LIST_MAX = 100
const MEMBERS_MAX = 50
const ASSESSORS_MAX = 200

function isCandidate(user, role) {
  return user.status === 'active' && !user.roles.includes('entity') && grantedPermissionKeys(user.roles).includes(ROLE_PERMISSIONS[role])
}

function candidateIds(role) {
  return new Set(usersFixture.filter((user) => isCandidate(user, role)).map((user) => user.id))
}

export function listCandidates(query) {
  const role = query.get('role')
  const errors = {}
  if (!Object.hasOwn(ROLE_PERMISSIONS, role ?? '')) errors.role = [fieldMessage('validation.required', {}, 'role')]
  if ((query.get('search') ?? '').length > SEARCH_MAX) errors.search = [fieldMessage('validation.max_chars', { max: SEARCH_MAX }, 'filter')]
  throwIfErrors(errors)
  const needle = (query.get('search') ?? '').trim().toLowerCase()
  return usersFixture
    .filter((user) => isCandidate(user, role))
    .filter((user) => !needle || user.name.toLowerCase().includes(needle))
    .sort((a, b) => a.name.localeCompare(b.name) || a.id - b.id)
    .slice(0, LIST_MAX)
    .map((user) => ({ id: user.id, name: user.name }))
}

function idsError(ids, max) {
  return !Array.isArray(ids) || ids.length > max || new Set(ids).size !== ids.length || ids.some((id) => !isPositiveInteger(id))
}

function assertOpenRoster(user, cycle, body) {
  if (user.superAdmin && !trimmed(body.reason)) throw assessmentReject.reasonRequired()
  if (cycle.status === 'closed') throw assessmentReject.illegalTransition('closed', 'change_roster')
}

function validateMembers(body) {
  const errors = {}
  const keys = MEMBER_ROLES.map((role) => `${role.replace(/_([a-z])/g, (match, letter) => letter.toUpperCase())}Ids`)
  if (!keys.some((key) => Object.hasOwn(body, key))) errors.filteringOfficerIds = [fieldMessage('validation.required', {}, 'members')]
  keys.forEach((key) => {
    if (Object.hasOwn(body, key) && idsError(body[key], MEMBERS_MAX)) errors[key] = [fieldMessage('validation.invalid', {}, 'members')]
  })
  const reason = reasonError(body.reason)
  if (reason) errors.reason = reason
  throwIfErrors(errors)
  return Object.fromEntries(MEMBER_ROLES.map((role, index) => [role, Object.hasOwn(body, keys[index]) ? body[keys[index]].map(Number) : null]))
}

export function syncMembers(user, cycle, body) {
  const byRole = validateMembers(body)
  assertOpenRoster(user, cycle, body)
  Object.entries(byRole).forEach(([role, ids]) => {
    if (ids === null) return
    const allowed = candidateIds(role)
    if (ids.some((id) => !allowed.has(id))) throw assessmentReject.coded(422, 'cycle.member_invalid', `${role}_ids`)
    if (ids.some((id) => isInitialAssessor(cycle, id))) throw assessmentReject.coded(422, 'sod_conflict', `${role}_ids`)
  })
  Object.entries(byRole).forEach(([role, ids]) => {
    if (ids === null) return
    cycle.members = [...cycle.members.filter((member) => member.role !== role), ...ids.map((userId) => ({ userId, role }))]
  })
  cycle.updatedAt = nowIso()
  return cycle
}

export function syncInitialAssessors(user, cycle, body) {
  const errors = {}
  if (idsError(body.assessorIds, ASSESSORS_MAX)) errors.assessorIds = [fieldMessage('validation.invalid', {}, 'assessorIds')]
  const reason = reasonError(body.reason)
  if (reason) errors.reason = reason
  throwIfErrors(errors)
  assertOpenRoster(user, cycle, body)
  const ids = body.assessorIds.map(Number)
  const allowed = candidateIds('initial_assessor')
  if (ids.some((id) => !allowed.has(id))) throw assessmentReject.coded(422, 'cycle.initial_assessor_invalid', 'assessorIds')
  if (ids.some((id) => isMember(cycle, id))) throw assessmentReject.coded(422, 'sod_conflict', 'assessorIds')
  const kept = cycle.initialAssessors.map((link) => ({ ...link, isActive: ids.includes(link.assessorId) }))
  const added = ids.filter((id) => !kept.some((link) => link.assessorId === id)).map((assessorId) => ({ assessorId, isActive: true }))
  cycle.initialAssessors = [...kept, ...added]
  cycle.updatedAt = nowIso()
  return cycle
}
