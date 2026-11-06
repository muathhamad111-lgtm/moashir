import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { fromCanonicalStatus } from '@/utils/canonicalStatus'
import { currentSessionUser } from '../sessionStore'
import { sessionCan } from '../sessionPermissions'
import { ADVANCED_STATES, ENTITY_PLACEHOLDER, FILTERING_VISIBLE_STATES } from './advancedStates'
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
  if (
    record.path !== ASSESSMENT_PATHS.ADVANCED ||
    !FILTERING_VISIBLE_STATES.includes(status) ||
    isAnnounced(record)
  )
    return false
  if (isOwner(record, viewer)) return true
  return status !== ADVANCED_STATES.AWAITING_FILTERING && !readsResults(viewer)
}

export function maskedStatus(record, viewer = currentSessionUser()) {
  if (!hidesState(record, viewer, record.status)) return record.status
  return isOwner(record, viewer) ? ENTITY_PLACEHOLDER : ADVANCED_STATES.AWAITING_FILTERING
}

export function isMaskedFor(record, viewer = currentSessionUser()) {
  return hidesState(record, viewer, record.status)
}

export function visibleTransitions(record, viewer = currentSessionUser()) {
  return (record.transitions ?? []).filter(
    (step) => !hidesState(record, viewer, fromCanonicalStatus(step.to)),
  )
}

export function hiddenStatusesFor(viewer = currentSessionUser()) {
  if (!viewer) return []
  if (viewer.entityId !== null) return FILTERING_VISIBLE_STATES
  return readsResults(viewer)
    ? []
    : FILTERING_VISIBLE_STATES.filter((status) => status !== ADVANCED_STATES.AWAITING_FILTERING)
}
