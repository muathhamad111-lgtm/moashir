import { usersFixture } from '../fixtures/users'
import { ASSESSOR_QUALIFICATIONS_FIXTURE, REQUEST_PATHS_FIXTURE } from '../fixtures/userPathAccess'
import { grantedPermissionKeys } from './permissionsStore'
import { ROLES } from '@/constants/roles'
import { assessmentReject, fieldMessage } from './assessments/assessmentRejection'
import { reasonError, throwIfErrors, trimmed } from './accredited/accreditedValidation'

const ASSIGNABLE_ROLES = Object.values(ROLES).filter((role) => role !== ROLES.ENTITY)
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const NAME = { MIN: 2, MAX: 150 }
const ROLES_MAX = 10
const SORTABLE = ['name', 'lastLoginAt', 'createdAt']
const STATUSES = ['invited', 'active', 'locked', 'suspended', 'expired']
const QUALIFICATIONS = ['accredited', 'advanced_initial']
const REVIEWED = ['accredited', 'advanced']
const SCORE_PERMISSION = 'assessment.score.manage'
const QUEUE_PERMISSION = 'requests.queue.view'

let users = []
let nextId = 500000

export function resetStaffUsers() {
  users = usersFixture.map((user) => ({
    ...user,
    roles: [...user.roles],
    assessorQualifications: [...(ASSESSOR_QUALIFICATIONS_FIXTURE[user.id] ?? [])],
    requestPaths: [...(REQUEST_PATHS_FIXTURE[user.id] ?? [])],
  }))
  nextId = 500000
}

resetStaffUsers()

function staff() {
  return users.filter((user) => !user.roles.includes(ROLES.ENTITY))
}

export function findStaffUser(id) {
  return staff().find((user) => user.id === Number(id)) ?? null
}

export function qualificationsOf(userId) {
  return [...(findStaffUser(userId)?.assessorQualifications ?? [])]
}

export function requestScopeOf(userId, { superAdmin = false } = {}) {
  const granted = superAdmin ? [] : (findStaffUser(userId)?.requestPaths ?? [])
  return {
    allPaths: granted.length === 0,
    paths: REVIEWED.filter((path) => !granted.length || granted.includes(path)),
  }
}

export function userResource(user) {
  return {
    id: user.id,
    name: user.name,
    email: user.email,
    phone: user.phone,
    roles: [...user.roles],
    assessorQualifications: [...user.assessorQualifications],
    requestScope: requestScopeOf(user.id),
    status: user.status,
    mfaEnabled: user.mfaEnabled,
    lastLoginAt: user.lastLoginAt,
    entityId: user.entityId,
    createdAt: user.createdAt,
  }
}

function filtersOf(query) {
  const errors = {}
  const role = query.get('filter[role]')
  const status = query.get('filter[status]')
  const sort = query.get('sort') ?? 'name'
  if (role && !ASSIGNABLE_ROLES.includes(role))
    errors['filter.role'] = [fieldMessage('validation.invalid', {}, 'filter')]
  if (status && !STATUSES.includes(status))
    errors['filter.status'] = [fieldMessage('validation.invalid', {}, 'filter')]
  if (
    !SORTABLE.includes(
      sort.replace(/^-/, '').replace(/_([a-z])/g, (match, letter) => letter.toUpperCase()),
    )
  )
    errors.sort = [fieldMessage('validation.invalid', {}, 'sort')]
  throwIfErrors(errors)
  return { role, status, search: trimmed(query.get('search')).toLowerCase(), sort }
}

function compareBy(sort) {
  const descending = sort.startsWith('-')
  const field = sort.replace(/^-/, '').replace(/_([a-z])/g, (match, letter) => letter.toUpperCase())
  return (left, right) => {
    const a = left[field] ?? ''
    const b = right[field] ?? ''
    if (a === b) return left.id - right.id
    return (a < b ? -1 : 1) * (descending ? -1 : 1)
  }
}

export function listStaffUsers(query) {
  const filters = filtersOf(query)
  const searched = staff().filter(
    (user) =>
      !filters.search ||
      user.name.toLowerCase().includes(filters.search) ||
      user.email.toLowerCase().includes(filters.search),
  )
  const byRole = searched.filter((user) => !filters.role || user.roles.includes(filters.role))
  const rows = byRole
    .filter((user) => !filters.status || user.status === filters.status)
    .sort(compareBy(filters.sort))
  const count = (status) => byRole.filter((user) => user.status === status).length
  const roleCounts = Object.fromEntries(
    ASSIGNABLE_ROLES.map((role) => [
      role,
      staff().filter((user) => user.roles.includes(role)).length,
    ]),
  )
  return {
    rows,
    stats: {
      total: byRole.length,
      active: count('active'),
      locked: count('locked'),
      suspended: count('suspended'),
      invited: count('invited'),
      byRole: roleCounts,
    },
  }
}

function emailTaken(email, exceptId) {
  return users.some(
    (user) => user.email.toLowerCase() === email.toLowerCase() && user.id !== exceptId,
  )
}

function profileErrors(body, { partial, exceptId }) {
  const errors = {}
  if (!partial || 'name' in body) {
    const name = trimmed(body.name)
    if (name.length < NAME.MIN || name.length > NAME.MAX)
      errors.name = [
        fieldMessage('validation.between_chars', { min: NAME.MIN, max: NAME.MAX }, 'fullName'),
      ]
  }
  if (!partial || 'email' in body) {
    const email = trimmed(body.email)
    if (!EMAIL.test(email)) errors.email = [fieldMessage('validation.email', {}, 'email')]
    else if (emailTaken(email, exceptId))
      errors.email = [fieldMessage('validation.unique', {}, 'email')]
  }
  if (!partial || 'roles' in body) {
    const roles = Array.isArray(body.roles) ? body.roles : []
    if (
      !roles.length ||
      roles.length > ROLES_MAX ||
      roles.some((role) => !ASSIGNABLE_ROLES.includes(role))
    )
      errors.roles = [fieldMessage('validation.invalid', {}, 'roles')]
  }
  return errors
}

export function inviteStaffUser(body) {
  throwIfErrors(profileErrors(body, { partial: false, exceptId: null }))
  nextId += 1
  const user = {
    id: nextId,
    name: trimmed(body.name),
    email: trimmed(body.email),
    phone: body.phone ?? null,
    roles: [...body.roles],
    assessorQualifications: [],
    requestPaths: [],
    status: 'invited',
    mfaEnabled: false,
    lastLoginAt: null,
    entityId: null,
    createdAt: new Date().toISOString(),
  }
  users.push(user)
  return user
}

export function updateStaffUser(user, body) {
  throwIfErrors(profileErrors(body, { partial: true, exceptId: user.id }))
  if ('name' in body) user.name = trimmed(body.name)
  if ('email' in body) user.email = trimmed(body.email)
  if ('roles' in body) user.roles = [...body.roles]
  return user
}

function assertState(user, statuses, verb) {
  if (!statuses.includes(user.status)) throw assessmentReject.illegalTransition(user.status, verb)
}

function assertNotSelf(actor, user) {
  if (actor.id === user.id) throw assessmentReject.coded(422, 'user.self')
}

function reasonOf(body, required) {
  const fault = reasonError(body.reason, { required })
  if (fault) throw assessmentReject.validation({ reason: fault })
}

export function suspendStaffUser(actor, user, body) {
  reasonOf(body, true)
  assertNotSelf(actor, user)
  assertState(user, ['active'], 'suspend')
  user.status = 'suspended'
  return user
}

export function activateStaffUser(user, body) {
  reasonOf(body, false)
  assertState(user, ['suspended'], 'activate')
  user.status = 'active'
  return user
}

export function unlockStaffUser(user, body) {
  reasonOf(body, false)
  assertState(user, ['locked'], 'unlock')
  user.status = 'active'
  return user
}

export function resetStaffMfa(actor, user, body) {
  reasonOf(body, true)
  assertNotSelf(actor, user)
  user.mfaEnabled = false
  return user
}

export function resendStaffInvitation(user) {
  assertState(user, ['invited', 'expired'], 'resend_invitation')
  user.status = 'invited'
}

function holds(user, permission) {
  return grantedPermissionKeys(user.roles).includes(permission)
}

function pathAccessReason(actor, body) {
  reasonOf(body, false)
  if (actor.superAdmin && !trimmed(body.reason)) throw assessmentReject.reasonRequired()
}

function assertOther(actor, user) {
  if (actor.id === user.id) throw assessmentReject.coded(422, 'sod_self_grant')
}

function qualificationErrors(body) {
  const list = body.qualifications
  const valid =
    Array.isArray(list) &&
    new Set(list).size === list.length &&
    list.every((item) => QUALIFICATIONS.includes(item))
  return valid ? {} : { qualifications: [fieldMessage('validation.invalid', {}, 'settings')] }
}

export function setAssessorQualifications(actor, user, body) {
  throwIfErrors(qualificationErrors(body))
  pathAccessReason(actor, body)
  assertOther(actor, user)
  if (body.qualifications.length && !holds(user, SCORE_PERMISSION))
    throw assessmentReject.coded(422, 'assessor_qualification.not_assessor', 'qualifications')
  user.assessorQualifications = QUALIFICATIONS.filter((item) => body.qualifications.includes(item))
  return user
}

function scopeErrors(body) {
  if (typeof body.allPaths !== 'boolean')
    return { allPaths: [fieldMessage('validation.boolean', {}, 'settings')] }
  if (body.allPaths) return {}
  const paths = body.paths
  const valid =
    Array.isArray(paths) &&
    paths.length > 0 &&
    new Set(paths).size === paths.length &&
    paths.every((path) => REVIEWED.includes(path))
  return valid ? {} : { paths: [fieldMessage('validation.invalid', {}, 'settings')] }
}

export function setRequestScope(actor, user, body) {
  throwIfErrors(scopeErrors(body))
  pathAccessReason(actor, body)
  assertOther(actor, user)
  if (!body.allPaths && !holds(user, QUEUE_PERMISSION))
    throw assessmentReject.coded(422, 'request_scope.not_follower', 'paths')
  user.requestPaths = body.allPaths ? [] : REVIEWED.filter((path) => body.paths.includes(path))
  return user
}
