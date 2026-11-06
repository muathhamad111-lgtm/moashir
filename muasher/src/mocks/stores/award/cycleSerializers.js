import { findRegion } from '../regionsStore'
import { staffNameOf } from '../accredited/staffDirectory'
import { allAssessments } from '../assessments/assessmentsStore'
import { ADVANCED_STATES, isInAdvancedQueue } from './advancedStates'
import { acceptsApplications } from './cycleStore'
import { canManage, revealsStaff } from './cycleAccess'

function placeOf(regionId) {
  const region = findRegion(regionId)
  return region ? { id: region.id, code: region.code, name: { ...region.name } } : null
}

function requestsOf(cycle) {
  return allAssessments().filter((record) => record.awardCycleId === cycle.id)
}

function countsOf(cycle) {
  const requests = requestsOf(cycle)
  return {
    requests: requests.filter((record) => isInAdvancedQueue(record.status)).length,
    qualified: requests.filter((record) => record.status === ADVANCED_STATES.QUALIFIED).length,
  }
}

export function cycleResource(cycle, { withCounts = false } = {}) {
  return {
    id: cycle.id,
    name: { ...cycle.name },
    year: cycle.year,
    status: cycle.status,
    criteriaVersionId: cycle.criteriaVersionId,
    scaleKind: cycle.scaleKind,
    submissionStart: cycle.submissionStart,
    submissionEnd: cycle.submissionEnd,
    committeeStart: cycle.committeeStart,
    committeeEnd: cycle.committeeEnd,
    announcementAt: cycle.announcementAt,
    conditions: {
      minEntityAgeYears: cycle.minEntityAgeYears,
      disqualificationScore: cycle.disqualificationScore,
    },
    initialReviewSlaDays: cycle.initialReviewSlaDays,
    participationCertificateEnabled: cycle.participationCertificateEnabled,
    regions: cycle.regionIds.map(placeOf).filter(Boolean),
    counts: withCounts ? countsOf(cycle) : null,
    openedAt: cycle.openedAt,
    submissionsClosedAt: cycle.submissionsClosedAt,
    closedAt: cycle.closedAt,
    createdAt: cycle.createdAt,
    updatedAt: cycle.updatedAt,
  }
}

export function cycleDetailResource(cycle, viewer) {
  const detail = { ...cycleResource(cycle), canManage: canManage(viewer, cycle) }
  if (!revealsStaff(viewer, cycle)) return detail
  return {
    ...detail,
    members: cycle.members.map((member) => ({
      id: member.userId,
      name: staffNameOf(member.userId),
      role: member.role,
    })),
    initialAssessors: cycle.initialAssessors.map((link) => ({
      id: link.assessorId,
      name: staffNameOf(link.assessorId),
      isActive: link.isActive,
    })),
  }
}

export function publicCycleResource(cycle, now = Date.now()) {
  return {
    id: cycle.id,
    name: { ...cycle.name },
    year: cycle.year,
    status: cycle.status,
    isAcceptingApplications: acceptsApplications(cycle, now),
    submissionStart: cycle.submissionStart,
    submissionEnd: cycle.submissionEnd,
    committeeStart: cycle.committeeStart,
    committeeEnd: cycle.committeeEnd,
    announcementAt: cycle.announcementAt,
    conditions: { minEntityAgeYears: cycle.minEntityAgeYears },
    regions: cycle.regionIds.map(placeOf).filter(Boolean),
  }
}
