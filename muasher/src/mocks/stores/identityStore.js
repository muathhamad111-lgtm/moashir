import { nationalIdProblem } from '@/utils/identity/nationalId'
import { activeScenario } from './committee/committeeScenario'

export const IDENTITY_CHANGE_PENDING_SCENARIO = 'identity-change-pending'
// Staff accounts start without a gender here, so the completion modal and banner show.
export const ACCOUNT_COMPLETION_SCENARIO = 'account-completion'
const SEEDED_REQUESTER = Object.freeze({ id: 9, name: 'Laura Bennett' })

const STATUSES = Object.freeze({
  PENDING: 'pending',
  APPROVED: 'approved',
  REJECTED: 'rejected',
  CANCELLED: 'cancelled',
})
const VISIBLE_DIGITS = 4
const MASK = '•'

let nationalIds = new Map()
let genders = new Map()
let changedAtAcceptance = new Map()
let changes = []
let history = []
let sequence = 0

function seedScenario() {
  if (activeScenario() !== IDENTITY_CHANGE_PENDING_SCENARIO) return
  openChange({
    user: SEEDED_REQUESTER,
    submitter: SEEDED_REQUESTER,
    newName: 'Laura M. Bennett',
    reason: 'Correcting the name to match the official passport.',
  })
}

export function resetIdentityStore() {
  nationalIds = new Map()
  genders = new Map()
  changedAtAcceptance = new Map()
  changes = []
  history = []
  sequence = 0
  seedScenario()
}

export function maskNationalId(value) {
  if (!value) return null
  return MASK.repeat(value.length - VISIBLE_DIGITS) + value.slice(-VISIBLE_DIGITS)
}

export function nationalIdOf(userId) {
  return nationalIds.get(userId) ?? null
}

export function isNationalIdTaken(value, ownerId) {
  return [...nationalIds.entries()].some(([id, stored]) => stored === value && id !== ownerId)
}

// The rule the number breaks (digits, length, prefix), or null.
export function nationalIdProblemOf(value) {
  return nationalIdProblem(value)
}

function recordDirect(userId, oldValue, newValue, { source, actor }) {
  history.unshift({
    id: history.length + 1,
    userId,
    field: 'national_id',
    oldValue,
    newValue,
    source,
    requestId: null,
    reason: null,
    requestedBy: actor ? { id: actor.id, name: actor.name } : null,
    approvedBy: source === 'admin_entry' && actor ? { id: actor.id, name: actor.name } : null,
    createdAt: new Date().toISOString(),
  })
}

// Saved without an approval request: by an administrator, the Super Admin, or at entity registration.
export function assignNationalId(userId, value, entry = { source: 'admin_entry', actor: null }) {
  recordDirect(userId, null, value, entry)
  nationalIds.set(userId, value)
}

export function replaceAtAcceptance(invitee, value) {
  const previous = nationalIdOf(invitee.id)
  if (previous === value) return false
  recordDirect(invitee.id, previous, value, { source: 'invitation_acceptance', actor: invitee })
  nationalIds.set(invitee.id, value)
  changedAtAcceptance.set(invitee.id, new Date().toISOString())
  return true
}

export function nationalIdChangedAtAcceptanceOf(userId) {
  return changedAtAcceptance.get(userId) ?? null
}

export function genderOf(userId, fallback = null) {
  return genders.get(userId) ?? (activeScenario() === ACCOUNT_COMPLETION_SCENARIO ? null : fallback)
}

export function genderMissingFor(descriptor) {
  if (descriptor.roles.includes('entity')) return false
  return activeScenario() === ACCOUNT_COMPLETION_SCENARIO && !genders.has(descriptor.id)
}

export function setGender(userId, gender) {
  genders.set(userId, gender)
}

export function nationalIdStateOf(userId, required) {
  if (nationalIdOf(userId)) return 'provided'
  if (pendingChangeOf(userId)?.newNationalId) return 'pending'
  return required ? 'missing' : 'not_required'
}

function nextId() {
  sequence += 1
  return `00000000-0000-4000-8000-${String(sequence).padStart(12, '0')}`
}

export function pendingChangeOf(userId) {
  return changes.find((change) => change.userId === userId && change.status === STATUSES.PENDING)
}

export function openChange({
  user,
  submitter,
  newName,
  newNationalId,
  reason,
  kind = 'correction',
}) {
  const change = {
    id: nextId(),
    kind,
    userId: user.id,
    userName: user.name,
    submitter: submitter ? { id: submitter.id, name: submitter.name } : null,
    newName: newName ?? null,
    newNationalId: newNationalId ?? null,
    reason: reason ?? null,
    status: STATUSES.PENDING,
    reviewer: null,
    reviewNote: null,
    createdAt: new Date().toISOString(),
    decidedAt: null,
  }
  changes.unshift(change)
  return change
}

export function findChange(id) {
  return changes.find((change) => change.id === id) ?? null
}

export function changesOf(userId) {
  return changes.filter((change) => change.userId === userId)
}

export function listChanges(status) {
  return status && status !== 'all' ? changes.filter((change) => change.status === status) : changes
}

function decide(change, status, reviewer, note) {
  change.status = status
  change.reviewer = { id: reviewer.id, name: reviewer.name }
  change.reviewNote = note ?? null
  change.decidedAt = new Date().toISOString()
}

function record(change, field, oldValue, newValue, reviewer) {
  history.unshift({
    id: history.length + 1,
    userId: change.userId,
    field,
    oldValue,
    newValue,
    source: 'approved_request',
    requestId: change.id,
    reason: change.reason,
    requestedBy: change.submitter,
    approvedBy: { id: reviewer.id, name: reviewer.name },
    createdAt: new Date().toISOString(),
  })
}

export function approveChange(change, reviewer, note) {
  if (change.newName) record(change, 'name', change.userName, change.newName, reviewer)
  if (change.newNationalId) {
    record(change, 'national_id', nationalIdOf(change.userId), change.newNationalId, reviewer)
    nationalIds.set(change.userId, change.newNationalId)
  }
  decide(change, STATUSES.APPROVED, reviewer, note)
}

export function rejectChange(change, reviewer, note) {
  decide(change, STATUSES.REJECTED, reviewer, note)
}

export function cancelChange(change) {
  change.status = STATUSES.CANCELLED
  change.decidedAt = new Date().toISOString()
}

export function historyOf(userId) {
  return history.filter((entry) => entry.userId === userId)
}

export { STATUSES as IDENTITY_CHANGE_STATUSES }

seedScenario()
