import { toCanonicalStatus } from '@/utils/canonicalStatus'
import { assessmentReject } from '../assessments/assessmentRejection'
import { allAssessments, isFrozen } from '../assessments/assessmentsStore'
import { ACTORS, transition } from '../assessments/statusHistory'
import { nowIso } from '../accredited/accreditedRecords'
import { optionalReason, reasonError, trimmed } from '../accredited/accreditedValidation'
import { ADVANCED_STATES, isAdvancedOpen } from './advancedStates'
import { allCycles, runningCycle } from './cycleStore'
import { awardReject } from './awardRejection'
import { announceCycleClosed, announceCycleOpened, announceSubmissionsClosed } from './awardNotices'

const CLOSABLE = ['submission-closed', 'committee-assessment', 'awaiting-secretary', 'approved', 'announced']
const REOPEN_REASON_MIN = 20

function actionReason(user, body) {
  const reason = optionalReason(body)
  if (user.superAdmin && !reason) throw assessmentReject.reasonRequired()
  return reason
}

function assertStatus(cycle, statuses, verb) {
  if (!statuses.includes(cycle.status)) throw assessmentReject.illegalTransition(toCanonicalStatus(cycle.status), verb)
}

function requestsOf(cycle) {
  return allAssessments().filter((record) => record.awardCycleId === cycle.id)
}

function openSubmissions(cycle, at) {
  const startsLater = Date.parse(cycle.submissionStart) > Date.parse(at)
  Object.assign(cycle, { status: 'submission-open', openedAt: at, submissionStart: startsLater ? at : cycle.submissionStart, updatedAt: at })
  announceCycleOpened(cycle)
}

function expireDrafts(cycle, at) {
  requestsOf(cycle)
    .filter((record) => record.status === ADVANCED_STATES.DRAFT && !isFrozen(record))
    .forEach((record) => {
      record.transitions.push(transition(ADVANCED_STATES.DRAFT, ADVANCED_STATES.EXPIRED, 'expire_draft', at, ACTORS.SYSTEM))
      Object.assign(record, { status: ADVANCED_STATES.EXPIRED, updatedAt: at })
    })
}

function closeWindow(cycle, at) {
  const endsLater = Date.parse(cycle.submissionEnd) > Date.parse(at)
  Object.assign(cycle, { status: 'submission-closed', submissionsClosedAt: at, submissionEnd: endsLater ? at : cycle.submissionEnd, updatedAt: at })
  expireDrafts(cycle, at)
  announceSubmissionsClosed(cycle)
}

export function openCycle(user, cycle, body) {
  actionReason(user, body)
  assertStatus(cycle, ['draft'], 'open')
  const other = runningCycle(cycle.id)
  if (other) throw awardReject.anotherOpen(other.id)
  if (Date.parse(cycle.submissionEnd) <= Date.now()) throw assessmentReject.coded(409, 'cycle.submission_window_passed')
  openSubmissions(cycle, nowIso())
  return cycle
}

export function closeCycleSubmissions(user, cycle, body) {
  actionReason(user, body)
  assertStatus(cycle, ['submission-open'], 'close_submissions')
  closeWindow(cycle, nowIso())
  return cycle
}

export function closeCycle(user, cycle, body) {
  actionReason(user, body)
  assertStatus(cycle, CLOSABLE, 'close')
  const pending = requestsOf(cycle).filter((record) => isAdvancedOpen(record.status) && !isFrozen(record))
  if (pending.length) throw awardReject.requestsPending(pending.map((record) => ({ id: record.id, number: record.number, status: record.status })))
  const at = nowIso()
  Object.assign(cycle, { closedFromStatus: cycle.status, status: 'closed', closedAt: at, updatedAt: at })
  announceCycleClosed(cycle)
  return cycle
}

export function reopenCycle(user, cycle, body) {
  const error = reasonError(body.reason, { required: true, min: REOPEN_REASON_MIN })
  if (error) throw assessmentReject.validation({ reason: error })
  assertStatus(cycle, ['closed'], 'reopen')
  const other = runningCycle(cycle.id)
  if (other) throw awardReject.anotherOpen(other.id)
  Object.assign(cycle, { status: cycle.closedFromStatus ?? 'submission-closed', closedAt: null, updatedAt: nowIso(), reopenReason: trimmed(body.reason) })
  return cycle
}

export function runAwardSchedule(now = Date.now()) {
  const at = new Date(now).toISOString()
  allCycles()
    .filter((cycle) => cycle.status === 'submission-open' && Date.parse(cycle.submissionEnd) <= now)
    .forEach((cycle) => closeWindow(cycle, at))
  allCycles()
    .filter((cycle) => cycle.status === 'draft' && Date.parse(cycle.submissionStart) <= now && Date.parse(cycle.submissionEnd) > now)
    .filter((cycle) => !runningCycle(cycle.id))
    .forEach((cycle) => openSubmissions(cycle, at))
}
