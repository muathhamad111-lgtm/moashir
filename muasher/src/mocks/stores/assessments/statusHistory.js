import { SELF_STATUSES } from '@/constants/assessment'
import { toCanonicalStatus } from '@/utils/canonicalStatus'
import { usersFixture } from '../../fixtures/users'
import { findRole } from '../permissionsStore'

export const ACTORS = { ENTITY: 'entity', STAFF: 'staff', SYSTEM: 'system' }
export const TRANSITION_VERBS = {
  START: 'start',
  SUBMIT: 'submit',
  COMPUTE_RESULT: 'compute_result',
}

export function transition(from, to, verb, at, actor, actorUserId = null) {
  return {
    from: from ? toCanonicalStatus(from) : null,
    to: toCanonicalStatus(to),
    verb,
    at,
    actor,
    actorUserId,
  }
}

export function visibleActorId(user) {
  return user.superAdmin ? null : user.id
}

export function actorOf(user, record) {
  return user.entityId !== null && Number(user.entityId) === record.entityId
    ? ACTORS.ENTITY
    : ACTORS.STAFF
}

export function seedTransitions(record) {
  const steps = [
    transition(null, SELF_STATUSES.DRAFT, TRANSITION_VERBS.START, record.createdAt, ACTORS.ENTITY),
  ]
  if (record.submittedAt)
    steps.push(
      transition(
        SELF_STATUSES.DRAFT,
        SELF_STATUSES.SUBMITTED,
        TRANSITION_VERBS.SUBMIT,
        record.submittedAt,
        ACTORS.ENTITY,
      ),
    )
  if (record.completedAt) {
    steps.push(
      transition(
        SELF_STATUSES.SUBMITTED,
        SELF_STATUSES.RESULT_AVAILABLE,
        TRANSITION_VERBS.COMPUTE_RESULT,
        record.completedAt,
        ACTORS.SYSTEM,
      ),
    )
  }
  return steps
}

export function recordStart(record, user, at) {
  record.transitions = [
    transition(
      null,
      SELF_STATUSES.DRAFT,
      TRANSITION_VERBS.START,
      at,
      actorOf(user, record),
      visibleActorId(user),
    ),
  ]
}

export function recordSubmission(record, user, at) {
  record.transitions.push(
    transition(
      SELF_STATUSES.DRAFT,
      SELF_STATUSES.SUBMITTED,
      TRANSITION_VERBS.SUBMIT,
      at,
      actorOf(user, record),
      visibleActorId(user),
    ),
    transition(
      SELF_STATUSES.SUBMITTED,
      SELF_STATUSES.RESULT_AVAILABLE,
      TRANSITION_VERBS.COMPUTE_RESULT,
      at,
      ACTORS.SYSTEM,
    ),
  )
}

function staffOf(userId) {
  const staff = userId === null ? null : usersFixture.find((item) => item.id === userId)
  if (!staff) return { name: null, roles: [] }
  return {
    name: staff.name,
    roles: staff.roles.map((key) => ({ key, label: findRole(key)?.label ?? { ar: key, en: key } })),
  }
}

export function statusHistoryOf(record, { staffView }) {
  return (record.transitions ?? []).map((step) => {
    const entry = { from: step.from, to: step.to, verb: step.verb, at: step.at, actor: step.actor }
    if (!staffView) return entry
    return { ...entry, actorStaff: step.actor === ACTORS.STAFF ? staffOf(step.actorUserId) : null }
  })
}
