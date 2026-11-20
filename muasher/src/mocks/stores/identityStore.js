import { isValidNationalId } from '@/utils/identity/nationalId'
import { activeScenario } from './committee/committeeScenario'

export const IDENTITY_CHANGE_PENDING_SCENARIO = 'identity-change-pending'
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

export function validNationalId(value) {
  return isValidNationalId(value)
}

export function assignNationalId(userId, value) {
  nationalIds.set(userId, value)
}

function nextId() {
  sequence += 1
  return `00000000-0000-4000-8000-${String(sequence).padStart(12, '0')}`
}

export function pendingChangeOf(userId) {
  return changes.find((change) => change.userId === userId && change.status === STATUSES.PENDING)
}

export function openChange({ user, submitter, newName, newNationalId, reason }) {
  const change = {
    id: nextId(),
    userId: user.id,
    userName: user.name,
    submitter: submitter ? { id: submitter.id, name: submitter.name } : null,
    newName: newName ?? null,
    newNationalId: newNationalId ?? null,
    reason,
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
