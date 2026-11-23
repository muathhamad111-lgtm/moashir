import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { fromCanonicalStatus, toCanonicalStatus } from '@/utils/canonicalStatus'
import { currentSessionUser } from '../sessionStore'
import { sessionCan } from '../sessionPermissions'
import {
  ADVANCED_STATES,
  ENTITY_PLACEHOLDER,
  FILTERING_VISIBLE_STATES,
  OWNER_HIDDEN_STATES,
} from './advancedStates'
import { findCycle } from './cycleStore'

const RESULT_READER_PERMISSIONS = [
  'requests.queue.view',
  'award.filtering.view',
  'award.secretary.review',
]

function isOwner(record, viewer) {
  return Boolean(viewer) && viewer.entityId !== null && Number(viewer.entityId) === record.entityId
}

function readsResults(viewer) {
  return (
    Boolean(viewer) &&
    (viewer.superAdmin || RESULT_READER_PERMISSIONS.some((key) => sessionCan(key)))
  )
}

function isAnnounced(record) {
  return Boolean(findCycle(record.awardCycleId)?.announcedAt)
}

function hidesState(record, viewer, status) {
  if (record.path !== ASSESSMENT_PATHS.ADVANCED || isAnnounced(record)) return false
  if (isOwner(record, viewer)) return OWNER_HIDDEN_STATES.includes(status)
  return (
    FILTERING_VISIBLE_STATES.includes(status) &&
    status !== ADVANCED_STATES.AWAITING_FILTERING &&
    !readsResults(viewer)
  )
}

function maskedValue(record, viewer, status) {
  if (!hidesState(record, viewer, status)) return status
  return isOwner(record, viewer) ? ENTITY_PLACEHOLDER : ADVANCED_STATES.AWAITING_FILTERING
}

export function maskedStatus(record, viewer = currentSessionUser()) {
  return maskedValue(record, viewer, record.status)
}

export function isMaskedFor(record, viewer = currentSessionUser()) {
  return hidesState(record, viewer, record.status)
}

function maskedStep(record, viewer, token) {
  return token ? toCanonicalStatus(maskedValue(record, viewer, fromCanonicalStatus(token))) : token
}

// Mirrors StatusMask::showsHistoryStep: a step into a masked stage is dropped, except the owner's step into it from
// a visible state (its submission or resubmission), which reads as the placeholder.
function showsStep(record, viewer, step) {
  if (!hidesState(record, viewer, fromCanonicalStatus(step.to))) return true
  return (
    isOwner(record, viewer) &&
    Boolean(step.from) &&
    !hidesState(record, viewer, fromCanonicalStatus(step.from))
  )
}

// Mirrors AssessmentRecordResource (AJ-03): the kept steps show both ends masked like the current status.
export function visibleTransitions(record, viewer = currentSessionUser()) {
  return (record.transitions ?? [])
    .filter((step) => showsStep(record, viewer, step))
    .map((step) => ({
      ...step,
      from: maskedStep(record, viewer, step.from),
      to: maskedStep(record, viewer, step.to),
    }))
}

export function hiddenStatusesFor(viewer = currentSessionUser()) {
  if (!viewer) return []
  if (viewer.entityId !== null) return OWNER_HIDDEN_STATES
  return readsResults(viewer)
    ? []
    : FILTERING_VISIBLE_STATES.filter((status) => status !== ADVANCED_STATES.AWAITING_FILTERING)
}
