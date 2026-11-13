import { paginate } from '../../contract/pagination'
import { assessmentReject, fieldMessage } from '../assessments/assessmentRejection'
import { findAssessment, isFrozen } from '../assessments/assessmentsStore'
import { staffNameOf } from '../accredited/staffDirectory'
import { assertNotFrozen } from '../accredited/entityWorkflow'
import {
  lengthError,
  reasonError,
  throwIfErrors,
  trimmed,
} from '../accredited/accreditedValidation'
import { ADVANCED_STATES as S } from '../award/advancedStates'
import {
  committeeState,
  currentEntriesOf,
  findSeat,
  insert,
  liveRowsOfRecord,
  openDiscussionOf,
} from './committeeState'
import { assertState, moveRecord, nowIso } from './committeeLifecycle'
import { committeeEntityOf } from './committeeEntity'
import { requireReason } from './committeeAccess'
import { announceDiscussionOpened } from './committeeNotices'

const MODES = ['session', 'agreed_outcome']
const KINDS_OF_MODE = { session: ['opinion'], agreed_outcome: ['approve', 'request_edit'] }
const ALL_KINDS = ['opinion', 'approve', 'request_edit']
const TEXT = { MIN: 10, MAX: 5000 }
const STATUSES = ['open', 'closed', 'cancelled']

function header(discussion) {
  return {
    id: discussion.id,
    assessmentId: discussion.assessmentId,
    mode: discussion.mode,
    status: discussion.status,
    deadlineAt: discussion.deadlineAt,
    agreedOutcome: discussion.agreedOutcome,
    openedAt: discussion.createdAt,
    closedAt: discussion.closedAt,
  }
}

export function discussionResource(discussion) {
  return {
    ...header(discussion),
    openedBy: {
      id: discussion.openedById,
      name: discussion.openedById === null ? null : staffNameOf(discussion.openedById),
    },
    summary: discussion.summary,
    entries: currentEntriesOf(discussion.id).map((entry) => {
      const seat = findSeat(entry.seatId)
      return {
        id: entry.id,
        member: { id: seat.id, name: staffNameOf(seat.userId) },
        kind: entry.kind,
        body: entry.body,
        at: entry.createdAt,
      }
    }),
  }
}

function acceptsEntries(discussion) {
  return (
    discussion.status === 'open' &&
    (discussion.deadlineAt === null || Date.parse(discussion.deadlineAt) > Date.now())
  )
}

function seatIdsOf(user, discussion) {
  return liveRowsOfRecord(discussion.assessmentId)
    .map((row) => findSeat(row.seatId))
    .filter((seat) => seat.userId === user.id)
    .map((seat) => seat.id)
}

export function memberDiscussionResource(discussion, user) {
  const mine = seatIdsOf(user, discussion)
  const entries = currentEntriesOf(discussion.id).map((entry) => ({
    id: entry.id,
    kind: entry.kind,
    body: entry.body,
    isMine: mine.includes(entry.seatId),
    at: entry.createdAt,
  }))
  return {
    ...header(discussion),
    entity: committeeEntityOf(findAssessment(discussion.assessmentId)),
    acceptsEntries: acceptsEntries(discussion),
    myEntry: entries.find((entry) => entry.isMine) ?? null,
    entries,
  }
}

export function myDiscussions(user, query) {
  const status = query.get('filter[status]')
  if (status && !STATUSES.includes(status))
    throw assessmentReject.validation({
      'filter.status': [fieldMessage('validation.invalid', {}, 'filter')],
    })
  const rows = committeeState.discussions
    .filter(
      (discussion) =>
        seatIdsOf(user, discussion).length && !isFrozen(findAssessment(discussion.assessmentId)),
    )
    .filter((discussion) => !status || discussion.status === status)
    .sort((left, right) => right.id - left.id)
  return paginate(rows, query)
}

function validatedOpening(body) {
  const errors = {}
  if (!MODES.includes(body.mode)) errors.mode = [fieldMessage('validation.required', {}, 'mode')]
  if (body.mode === 'session') {
    const time = typeof body.deadlineAt === 'string' ? Date.parse(body.deadlineAt) : Number.NaN
    if (Number.isNaN(time))
      errors.deadlineAt = [fieldMessage('validation.required', {}, 'deadlineAt')]
    else if (time <= Date.now())
      errors.deadlineAt = [fieldMessage('validation.after_now', {}, 'deadlineAt')]
  }
  if (body.mode === 'agreed_outcome') {
    const fault = lengthError(body.agreedOutcome, {
      min: TEXT.MIN,
      max: TEXT.MAX,
      attribute: 'agreedOutcome',
    })
    if (fault) errors.agreedOutcome = fault
  }
  const reason = reasonError(body.reason)
  if (reason) errors.reason = reason
  throwIfErrors(errors)
}

export function openDiscussion(user, cycle, record, body) {
  validatedOpening(body)
  requireReason(user, trimmed(body.reason) || null)
  assertNotFrozen(record)
  assertState(record, [S.AWAITING_SECRETARY], 'open_discussion')
  const session = body.mode === 'session'
  const discussion = insert('discussions', {
    cycleId: cycle.id,
    assessmentId: record.id,
    mode: body.mode,
    status: 'open',
    deadlineAt: session ? new Date(body.deadlineAt).toISOString() : null,
    agreedOutcome: session ? null : trimmed(body.agreedOutcome),
    openedById: user.superAdmin ? null : user.id,
    summary: null,
    createdAt: nowIso(),
    closedAt: null,
  })
  moveRecord(record, S.COMMITTEE_DISCUSSION, 'open_discussion', user)
  announceDiscussionOpened(record)
  return discussion
}

export function closeDiscussion(user, cycle, record, discussion, body) {
  if (
    body.summary !== undefined &&
    body.summary !== null &&
    (typeof body.summary !== 'string' || body.summary.length > TEXT.MAX)
  ) {
    throw assessmentReject.validation({
      summary: [fieldMessage('validation.max_chars', { max: TEXT.MAX }, 'summary')],
    })
  }
  const reason = reasonError(body.reason)
  if (reason) throw assessmentReject.validation({ reason })
  requireReason(user, trimmed(body.reason) || null)
  if (discussion.status !== 'open') throw assessmentReject.coded(409, 'discussion.closed')
  assertState(record, [S.COMMITTEE_DISCUSSION], 'close_discussion')
  Object.assign(discussion, {
    status: 'closed',
    closedAt: nowIso(),
    summary: trimmed(body.summary) || null,
  })
  moveRecord(record, S.AWAITING_SECRETARY, 'close_discussion', user)
  return discussion
}

export function findRecordDiscussion(record, discussionId) {
  const discussion = committeeState.discussions.find(
    (item) => item.id === Number(discussionId) && item.assessmentId === record.id,
  )
  if (!discussion) throw assessmentReject.notFound()
  return discussion
}

function validatedEntry(discussion, body) {
  const errors = {}
  if (!ALL_KINDS.includes(body.kind))
    errors.kind = [fieldMessage('validation.required', {}, 'kind')]
  if (body.kind !== 'approve') {
    const fault = lengthError(body.body, { min: TEXT.MIN, max: TEXT.MAX, attribute: 'body' })
    if (fault) errors.body = fault
  }
  throwIfErrors(errors)
  if (!KINDS_OF_MODE[discussion.mode].includes(body.kind))
    throw assessmentReject.coded(422, 'discussion.entry_kind_invalid', 'kind')
}

export function respondToDiscussion(user, discussionId, body) {
  const discussion = committeeState.discussions.find((item) => item.id === Number(discussionId))
  const activeSeat = discussion
    ? seatIdsOf(user, discussion)
        .map(findSeat)
        .find((seat) => seat.status === 'active')
    : null
  if (!discussion || !seatIdsOf(user, discussion).length) throw assessmentReject.notFound()
  if (user.superAdmin || !activeSeat) throw assessmentReject.forbidden()
  validatedEntry(discussion, body)
  assertNotFrozen(findAssessment(discussion.assessmentId))
  if (discussion.status !== 'open') throw assessmentReject.coded(409, 'discussion.closed')
  if (!acceptsEntries(discussion)) throw assessmentReject.coded(409, 'discussion.deadline_passed')
  const at = nowIso()
  committeeState.entries
    .filter(
      (entry) =>
        entry.discussionId === discussion.id &&
        entry.seatId === activeSeat.id &&
        entry.supersededAt === null,
    )
    .forEach((entry) => {
      entry.supersededAt = at
    })
  const entry = insert('entries', {
    discussionId: discussion.id,
    seatId: activeSeat.id,
    kind: body.kind,
    body: body.kind === 'approve' ? trimmed(body.body) || null : trimmed(body.body),
    createdAt: at,
    supersededAt: null,
  })
  return { id: entry.id, discussionId: discussion.id, kind: entry.kind, body: entry.body, at }
}

export function hasOpenDiscussion(record) {
  return Boolean(openDiscussionOf(record.id))
}
