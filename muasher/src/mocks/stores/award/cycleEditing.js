import { CRITERIA_VERSION_STATUSES } from '@/constants/criteria'
import { assessmentReject } from '../assessments/assessmentRejection'
import { allAssessments } from '../assessments/assessmentsStore'
import { findVersion } from '../criteriaStore'
import { sessionCan } from '../sessionPermissions'
import { nowIso } from '../accredited/accreditedRecords'
import { throwIfErrors, trimmed } from '../accredited/accreditedValidation'
import { cycleDefaults } from './cycleDefaults'
import { insertCycle } from './cycleStore'
import { managesEveryCycle } from './cycleAccess'
import {
  DATE_FIELDS,
  cycleErrors,
  datesOrdered,
  normalizedScore,
  storeDateOrderErrors,
} from './cycleValidation'
import { awardReject } from './awardRejection'

const RIYADH_OFFSET_MS = 3 * 60 * 60 * 1000
const SECRETARY_PERMISSION = 'award.secretary.decide'

const FIELD_STAGES = {
  draftOnly: { fields: ['submissionStart', 'criteriaVersionId', 'scaleKind'], statuses: ['draft'] },
  untilSubmissionsClose: {
    fields: ['submissionEnd', 'regionIds', 'minEntityAgeYears', 'disqualificationScore'],
    statuses: ['draft', 'submission-open'],
  },
  untilCommittee: {
    fields: ['committeeStart', 'committeeEnd', 'initialReviewSlaDays'],
    statuses: ['draft', 'submission-open', 'submission-closed'],
  },
}

const EDITABLE_FIELDS = [
  'name',
  'year',
  ...DATE_FIELDS,
  'regionIds',
  'criteriaVersionId',
  'scaleKind',
  'minEntityAgeYears',
  'disqualificationScore',
  'initialReviewSlaDays',
  'participationCertificateEnabled',
]

const WIRE_FIELD = { regionIds: 'region_ids' }

function wireName(field) {
  return WIRE_FIELD[field] ?? field.replace(/[A-Z]/g, (letter) => `_${letter.toLowerCase()}`)
}

function riyadhYear(iso) {
  return new Date(Date.parse(iso) + RIYADH_OFFSET_MS).getUTCFullYear()
}

function filled(value) {
  return value !== undefined && value !== null && value !== ''
}

function assertReason(user, body) {
  if (user.superAdmin && !trimmed(body.reason)) throw assessmentReject.reasonRequired()
}

function assertParticipation(user, body) {
  if (Object.hasOwn(body, 'participationCertificateEnabled') && !managesEveryCycle(user)) {
    throw assessmentReject.coded(403, 'cycle.participation_setting_reserved')
  }
}

function assertPublishedVersion(versionId) {
  if (findVersion(versionId)?.status !== CRITERIA_VERSION_STATUSES.PUBLISHED) {
    throw assessmentReject.coded(422, 'cycle.criteria_version_not_published', 'criteriaVersionId')
  }
}

function isoOf(value) {
  return filled(value) ? new Date(value).toISOString() : null
}

function attributesOf(body) {
  const attributes = {}
  if (Object.hasOwn(body, 'name'))
    attributes.name = { ar: body.name.ar.trim(), en: body.name.en.trim() }
  if (filled(body.year)) attributes.year = Number(body.year)
  DATE_FIELDS.forEach((field) => {
    if (Object.hasOwn(body, field)) attributes[field] = isoOf(body[field])
  })
  if (Object.hasOwn(body, 'regionIds')) attributes.regionIds = body.regionIds.map(Number)
  if (filled(body.criteriaVersionId)) attributes.criteriaVersionId = Number(body.criteriaVersionId)
  if (filled(body.scaleKind)) attributes.scaleKind = body.scaleKind
  if (filled(body.minEntityAgeYears)) attributes.minEntityAgeYears = Number(body.minEntityAgeYears)
  if (filled(body.disqualificationScore))
    attributes.disqualificationScore = normalizedScore(body.disqualificationScore)
  if (filled(body.initialReviewSlaDays))
    attributes.initialReviewSlaDays = Number(body.initialReviewSlaDays)
  if (Object.hasOwn(body, 'participationCertificateEnabled'))
    attributes.participationCertificateEnabled = body.participationCertificateEnabled
  return attributes
}

function creatorMembership(user) {
  if (user.superAdmin || managesEveryCycle(user) || !sessionCan(SECRETARY_PERMISSION)) return []
  return [{ userId: user.id, role: 'award_secretary' }]
}

export function createCycle(user, body) {
  throwIfErrors(cycleErrors(body, { required: true }))
  throwIfErrors(storeDateOrderErrors(body))
  assertReason(user, body)
  assertParticipation(user, body)
  assertPublishedVersion(body.criteriaVersionId)
  const attributes = attributesOf(body)
  const version = findVersion(attributes.criteriaVersionId)
  const at = nowIso()
  return insertCycle({
    year: riyadhYear(attributes.submissionStart),
    announcementAt: null,
    scaleKind: version.scaleKind,
    ...cycleDefaults(),
    participationCertificateEnabled: false,
    ...attributes,
    status: 'draft',
    members: creatorMembership(user),
    initialAssessors: [],
    createdById: user.superAdmin ? null : user.id,
    openedAt: null,
    submissionsClosedAt: null,
    closedAt: null,
    closedFromStatus: null,
    announcedAt: null,
    createdAt: at,
    updatedAt: at,
  })
}

function isEditable(status, field) {
  if (status === 'closed') return false
  const stage = Object.values(FIELD_STAGES).find((entry) => entry.fields.includes(field))
  return !stage || stage.statuses.includes(status)
}

function changedFields(cycle, attributes) {
  return Object.keys(attributes).filter(
    (field) => JSON.stringify(attributes[field]) !== JSON.stringify(cycle[field]),
  )
}

function assertRegionsKept(cycle, regionIds) {
  const removed = cycle.regionIds.filter((id) => !regionIds.includes(id))
  const used = allAssessments().some(
    (record) => record.awardCycleId === cycle.id && removed.includes(record.regionId),
  )
  if (used) throw assessmentReject.coded(422, 'cycle.region_has_requests', 'regionIds')
}

export function updateCycle(user, cycle, body) {
  throwIfErrors(cycleErrors(body, { required: false }))
  assertReason(user, body)
  assertParticipation(user, body)
  const attributes = attributesOf(body)
  const touched = Object.keys(attributes).filter((field) => EDITABLE_FIELDS.includes(field))
  const locked = touched.filter((field) => !isEditable(cycle.status, field))
  if (locked.length) throw awardReject.fieldsLocked(locked.map(wireName))
  if (attributes.criteriaVersionId !== undefined)
    assertPublishedVersion(attributes.criteriaVersionId)
  const dates = Object.fromEntries(
    DATE_FIELDS.map((field) => [
      field,
      Object.hasOwn(attributes, field) ? attributes[field] : cycle[field],
    ]),
  )
  if (!datesOrdered(dates, { submissionsOpen: cycle.status === 'submission-open' }))
    throw assessmentReject.coded(422, 'cycle.dates_unordered')
  if (attributes.regionIds) assertRegionsKept(cycle, attributes.regionIds)
  if (changedFields(cycle, attributes).length)
    Object.assign(cycle, attributes, { updatedAt: nowIso() })
  return cycle
}
