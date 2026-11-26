import { usersFixture } from '../fixtures/users'
import { ASSESSOR_QUALIFICATIONS_FIXTURE, REQUEST_PATHS_FIXTURE } from '../fixtures/userPathAccess'
import { grantedPermissionKeys } from './permissionsStore'
import { ROLES } from '@/constants/roles'
import { assessmentReject, fieldMessage } from './assessments/assessmentRejection'
import { reasonError, throwIfErrors, trimmed } from './accredited/accreditedValidation'
import { identityMessages } from '../fixtures/identityMessages'
import { GENDER_VALUES } from '@/constants/identityChanges'
import { normalizeNationalId } from '@/utils/identity/nationalId'
import {
  assignNationalId,
  genderOf,
  maskNationalId,
  nationalIdChangedAtAcceptanceOf,
  nationalIdOf,
  nationalIdProblemOf,
  setGender,
} from './identityStore'

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

const RESET_LINK_THROTTLE_MS = 60_000
const resetLinksSentAt = new Map()

export function resetStaffUsers() {
  resetLinksSentAt.clear()
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

export function staffHolding(permission) {
  return staff().filter(
    (user) => user.status === 'active' && grantedPermissionKeys(user.roles).includes(permission),
  )
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
    gender: genderOf(user.id, user.gender ?? null),
    nationalIdMasked: maskNationalId(nationalIdOf(user.id)),
    nationalIdChangedAtAcceptanceAt: nationalIdChangedAtAcceptanceOf(user.id),
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

// The list row: the personal fields (phone, gender, national id) stay on the single-user record.
export function userListResource(user) {
  const row = userResource(user)
  delete row.phone
  delete row.gender
  delete row.nationalIdMasked
  return row
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
  return { ...errors, ...identityErrors(body, partial) }
}

// Gender is required for staff; the national ID is optional and follows the operator's four rules.
function identityErrors(body, partial) {
  const errors = {}
  if ((!partial || 'gender' in body) && !GENDER_VALUES.includes(body.gender))
    errors.gender = [identityMessages['identity.gender_invalid']]
  const problem = body.nationalId ? nationalIdProblemOf(body.nationalId) : null
  if (problem) errors.nationalId = [identityMessages[`identity.national_id.${problem}`]]
  return errors
}

function recordIdentity(user, body) {
  if (body.gender) setGender(user.id, body.gender)
  if (!body.nationalId) return
  if (nationalIdOf(user.id)) throw assessmentReject.coded(409, 'identity.national_id_locked')
  assignNationalId(user.id, normalizeNationalId(body.nationalId), {
    source: 'admin_entry',
    actor: null,
  })
}

export function inviteStaffUser(body) {
  throwIfErrors(profileErrors(body, { partial: false, exceptId: null }))
  nextId += 1
  const user = {
    id: nextId,
    name: trimmed(body.name),
    email: trimmed(body.email),
    phone: body.phone ?? null,
    gender: body.gender,
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
  recordIdentity(user, body)
  return user
}

export function updateStaffUser(user, body) {
  throwIfErrors(profileErrors(body, { partial: true, exceptId: user.id }))
  recordIdentity(user, body)
  if ('name' in body) user.name = trimmed(body.name)
  if ('email' in body) user.email = trimmed(body.email)
  if ('roles' in body) user.roles = [...body.roles]
  if ('gender' in body) user.gender = body.gender
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

export function revokeStaffSessions(actor, user, body) {
  reasonOf(body, false)
  assertNotSelf(actor, user)
  return user
}

export function cancelStaffInvitation(actor, user) {
  assertNotSelf(actor, user)
  assertState(user, ['invited', 'expired'], 'cancel_invitation')
  users = users.filter((candidate) => candidate.id !== user.id)
}

const CSV_COLUMNS = ['name', 'email', 'roles', 'status', 'last_login_at', 'created_at']

function csvCell(value) {
  const text = String(value ?? '')
  return /[",\n]/.test(text) ? `"${text.replaceAll('"', '""')}"` : text
}

// Mirrors GET /users/export: the filtered list as CSV with a BOM so Excel reads Arabic.
export function staffUsersCsv(query) {
  const { rows } = listStaffUsers(query)
  const lines = rows.map((user) =>
    [user.name, user.email, user.roles.join(' | '), user.status, user.lastLoginAt, user.createdAt]
      .map(csvCell)
      .join(','),
  )
  return { csv: `\uFEFF${[CSV_COLUMNS.join(','), ...lines].join('\n')}\n`, count: rows.length }
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

// Mirrors SendUserPasswordResetLink: active users only, never the own account, one link per minute.
export function sendStaffPasswordResetLink(actor, user, now = Date.now()) {
  if (actor.id === user.id) throw assessmentReject.coded(422, 'user.self')
  assertState(user, ['active'], 'send_password_reset')
  if (now - (resetLinksSentAt.get(user.id) ?? -Infinity) < RESET_LINK_THROTTLE_MS) {
    throw assessmentReject.coded(429, 'user.password_reset_recently_sent')
  }
  resetLinksSentAt.set(user.id, now)
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
