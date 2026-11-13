import { CRITERIA_VERSION_STATUSES } from '@/constants/criteria'
import { awardCyclesFixture } from '../../fixtures/awardCycles'
import { versionWithStatus } from '../criteriaStore'
import { addDays } from '../accredited/dates'
import { scenarioAnchor, seededEntryFee } from './feeScenario'

const RUNNING_EXCLUDED = ['draft', 'closed']
const DATE_FIELDS = [
  'submissionStart',
  'submissionEnd',
  'committeeStart',
  'committeeEnd',
  'announcementAt',
  'announcedAt',
  'closedAt',
]

const state = { cycles: [], nextId: 100 }

function datesOf(offsets, anchor) {
  return Object.fromEntries(
    DATE_FIELDS.map((field) => [
      field,
      offsets[field] === null || offsets[field] === undefined
        ? null
        : addDays(anchor, offsets[field]),
    ]),
  )
}

function seededCycle(fixture, version, anchor) {
  const dates = datesOf(fixture.offsets, anchor)
  return {
    id: fixture.id,
    name: { ...fixture.name },
    year: new Date(dates.submissionStart).getUTCFullYear(),
    status: fixture.status,
    criteriaVersionId: version?.id ?? null,
    scaleKind: version?.scaleKind ?? 'of-10',
    ...dates,
    minEntityAgeYears: fixture.minEntityAgeYears,
    disqualificationScore: fixture.disqualificationScore,
    initialReviewSlaDays: fixture.initialReviewSlaDays,
    participationCertificateEnabled: fixture.participationCertificateEnabled,
    entryFee: seededEntryFee(fixture),
    regionIds: [...fixture.regionIds],
    members: fixture.members.map((member) => ({ ...member })),
    initialAssessors: fixture.initialAssessors.map((link) => ({ ...link })),
    createdById: 1,
    openedAt: fixture.status === 'draft' ? null : dates.submissionStart,
    submissionsClosedAt: ['draft', 'submission-open'].includes(fixture.status)
      ? null
      : dates.submissionEnd,
    closedFromStatus: fixture.closedFromStatus,
    createdAt: addDays(dates.submissionStart, -20),
    updatedAt: addDays(dates.submissionStart, -20),
  }
}

export function resetCycleStore(anchor = scenarioAnchor() ?? new Date().toISOString()) {
  const version = versionWithStatus(CRITERIA_VERSION_STATUSES.PUBLISHED)
  state.cycles = awardCyclesFixture.map((fixture) => seededCycle(fixture, version, anchor))
  state.nextId = Math.max(100, ...state.cycles.map((cycle) => cycle.id))
}

resetCycleStore()

export function allCycles() {
  return state.cycles
}

export function findCycle(id) {
  return state.cycles.find((cycle) => cycle.id === Number(id)) ?? null
}

export function insertCycle(fields) {
  state.nextId += 1
  const cycle = { id: state.nextId, ...fields }
  state.cycles.push(cycle)
  return cycle
}

export function isRunning(cycle) {
  return !RUNNING_EXCLUDED.includes(cycle.status)
}

export function runningCycle(exceptId = null) {
  return state.cycles.find((cycle) => isRunning(cycle) && cycle.id !== exceptId) ?? null
}

export function acceptsApplications(cycle, now = Date.now()) {
  return cycle.status === 'submission-open' && new Date(cycle.submissionEnd).getTime() > now
}

export function covers(cycle, regionId) {
  return cycle.regionIds.includes(Number(regionId))
}

export function membersOf(cycle, role) {
  return cycle.members.filter((member) => member.role === role).map((member) => member.userId)
}

export function isMember(cycle, userId, roles = ['filtering_officer', 'award_secretary']) {
  return cycle.members.some((member) => member.userId === userId && roles.includes(member.role))
}

export function isInitialAssessor(cycle, userId) {
  return cycle.initialAssessors.some((link) => link.assessorId === userId && link.isActive)
}

export function isLinkedAssessor(cycle, userId) {
  return cycle.initialAssessors.some((link) => link.assessorId === userId)
}
