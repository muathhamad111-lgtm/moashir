import { emailChangesOf } from './entityEmailChanges'
import { itemsOf, requestsOf } from './profileChangeRequests'
import { changesOf, staffChangeResource } from './profileChangesStore'

// The mock of GET /entities/{entity}/timeline: the entity's lifecycle dates, then (full record scope only)
// its profile changes, profile-change requests and e-mail changes, newest first.
const LIFECYCLE = [
  { kind: 'created', at: 'createdAt' },
  { kind: 'verification_reminded', at: 'verificationRemindedAt' },
  { kind: 'verified', at: 'verifiedAt' },
  { kind: 'approved', at: 'approvedAt' },
  { kind: 'rejected', at: 'rejectedAt', reason: 'rejectionReason' },
  { kind: 'deactivated', at: 'deactivatedAt', reason: 'deactivationReason' },
  { kind: 'restored', at: 'restoredAt' },
  { kind: 'deleted', at: 'deletedAt', reason: 'deletionReason' },
]
// Events sharing a timestamp keep the server's order (EntityTimelineKind::sequence): later steps first.
const KIND_ORDER = [
  'created',
  'verification_reminded',
  'verified',
  'approved',
  'rejected',
  'profile_completed',
  'marketing_consented',
  'marketing_unsubscribed',
  'deactivated',
  'restored',
  'deleted',
  'profile_field_changed',
  'profile_change_requested',
  'profile_change_decided',
  'email_change_requested',
  'email_change_decided',
]
const HIDDEN_EMAIL_REQUESTS = ['awaiting_otp', 'expired', 'cancelled']
const DECIDED_EMAIL = ['approved', 'rejected', 'applied']

function entry(kind, sourceId, occurredAt, extra = {}) {
  return {
    id: `${kind}:${sourceId}`,
    kind,
    occurredAt,
    actor: null,
    status: null,
    reason: null,
    fields: [],
    change: null,
    email: null,
    ...extra,
  }
}

function lifecycleOf(entity) {
  return LIFECYCLE.filter((event) => entity[event.at]).map((event) =>
    entry(event.kind, entity.id, entity[event.at], {
      reason: event.reason ? (entity[event.reason] ?? null) : null,
    }),
  )
}

function requestEntries(entity) {
  return requestsOf(entity.id).flatMap((request) => {
    const fields = itemsOf(request)
    const submitted = entry('profile_change_requested', request.id, request.submittedAt, { fields })
    if (!request.decidedAt) return [submitted]
    const decided = entry('profile_change_decided', request.id, request.decidedAt, {
      fields,
      status: request.status,
      reason: request.reviewNote,
      actor: request.reviewedBy ? { name: request.reviewedBy, roles: [] } : null,
    })
    return [decided, submitted]
  })
}

function emailEntries(entity) {
  return emailChangesOf(entity.id).flatMap((change) => {
    const email = { old: change.oldEmail, new: change.newEmail }
    const entries = []
    if (DECIDED_EMAIL.includes(change.status))
      entries.push(
        entry('email_change_decided', change.id, change.appliedAt ?? change.requestedAt, {
          email,
          status: change.status,
          reason: change.decisionReason,
        }),
      )
    if (!HIDDEN_EMAIL_REQUESTS.includes(change.status))
      entries.push(entry('email_change_requested', change.id, change.requestedAt, { email }))
    return entries
  })
}

function changeEntries(entity) {
  return changesOf(entity.id).map((change) =>
    entry('profile_field_changed', change.id, change.changedAt, {
      change: staffChangeResource(change),
    }),
  )
}

export function timelineOf(entity, { full }) {
  const entries = full
    ? [
        ...lifecycleOf(entity),
        ...changeEntries(entity),
        ...requestEntries(entity),
        ...emailEntries(entity),
      ]
    : lifecycleOf(entity)
  return entries.sort(
    (left, right) =>
      right.occurredAt.localeCompare(left.occurredAt) ||
      KIND_ORDER.indexOf(right.kind) - KIND_ORDER.indexOf(left.kind),
  )
}
