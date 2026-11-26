import { CRITERIA_VERSION_STATUSES as STATUS } from '@/constants/criteria'
import { versionWithStatus } from '../criteriaStore'
import { assessmentReject, fieldMessage } from '../assessments/assessmentRejection'
import { isOpen } from '../assessments/assessmentsStore'
import { dispatchQueuedJob } from '../mockQueue'
import { runningCycleCounts } from '../award/cycleConcurrency'
import {
  COMMITTEE_VISIBILITIES,
  CYCLE_CONCURRENCY_VALUES,
  SCALE_KIND_VALUES,
  EVIDENCE_TYPE_CATALOG,
  SLA_LIMITS,
  UPLOAD_LIMITS,
  assessmentSettings,
  writeAssessmentSettings,
} from './assessmentSettingsStore'
import { accreditedRecords } from './accreditedRecords'
import { addDays } from './dates'
import { reasonError, throwIfErrors, trimmed } from './accreditedValidation'

const INTEGER_RULES = {
  accreditedReviewSlaDays: SLA_LIMITS,
  uploadMaxFileMb: { min: 1, max: UPLOAD_LIMITS.maxFileMb },
  generalUploadMaxFileMb: { min: 1, max: UPLOAD_LIMITS.maxFileMb },
  evidenceMaxFilesPerSub: { min: 1, max: UPLOAD_LIMITS.maxFilesPerSub },
  advancedMinEntityAgeYears: { min: 0, max: 100 },
  advancedInitialReviewSlaDays: SLA_LIMITS,
}

const SCORE_PATTERN = /^\d{1,3}(\.\d{1,2})?$/

function scoreError(value) {
  const text = typeof value === 'number' ? String(value) : value
  const valid = typeof text === 'string' && SCORE_PATTERN.test(text) && Number(text) <= 100
  return valid ? null : [fieldMessage('validation.between', { min: 0, max: 100 }, 'settings')]
}

function twoDecimals(value) {
  return Number(value).toFixed(2)
}

function integerError(value, { min, max }) {
  return Number.isInteger(value) && value >= min && value <= max
    ? null
    : [fieldMessage('validation.between', { min, max }, 'settings')]
}

function typesError(value) {
  const valid =
    Array.isArray(value) &&
    value.length > 0 &&
    new Set(value).size === value.length &&
    value.every((type) => EVIDENCE_TYPE_CATALOG.includes(type))
  return valid ? null : [fieldMessage('validation.invalid', {}, 'settings')]
}

const CHOICE_RULES = {
  committeeEntityDataVisibility: COMMITTEE_VISIBILITIES,
  scaleKind: SCALE_KIND_VALUES,
  advancedCycleConcurrency: CYCLE_CONCURRENCY_VALUES,
}

function validateChoices(body, errors, changes) {
  Object.entries(CHOICE_RULES).forEach(([key, allowed]) => {
    if (!(key in body)) return
    if (allowed.includes(body[key])) changes[key] = body[key]
    else errors[key] = [fieldMessage('validation.invalid', {}, 'settings')]
  })
}

function validatedChanges(body) {
  const errors = {}
  const changes = {}
  Object.entries(INTEGER_RULES).forEach(([key, rule]) => {
    if (!(key in body)) return
    const error = integerError(body[key], rule)
    if (error) errors[key] = error
    else changes[key] = body[key]
  })
  if ('evidenceAllowedTypes' in body) {
    const error = typesError(body.evidenceAllowedTypes)
    if (error) errors.evidenceAllowedTypes = error
    else changes.evidenceAllowedTypes = [...body.evidenceAllowedTypes]
  }
  if ('advancedDisqualificationScore' in body) {
    const error = scoreError(body.advancedDisqualificationScore)
    if (error) errors.advancedDisqualificationScore = error
    else changes.advancedDisqualificationScore = twoDecimals(body.advancedDisqualificationScore)
  }
  validateChoices(body, errors, changes)
  const reason = reasonError(body.reason)
  if (reason) errors.reason = reason
  throwIfErrors(errors)
  return changes
}

function recomputeDueDates(slaDays) {
  accreditedRecords()
    .filter((record) => isOpen(record) && record.dueAt && record.reviewSlaDays !== slaDays)
    .forEach((record) => {
      record.dueAt = addDays(record.dueAt, slaDays - record.reviewSlaDays)
      record.reviewSlaDays = slaDays
    })
}

function switchesConcurrency(changes) {
  return (
    'advancedCycleConcurrency' in changes &&
    changes.advancedCycleConcurrency !== assessmentSettings().advancedCycleConcurrency
  )
}

export function updateAssessmentSettings(user, body) {
  const changes = validatedChanges(body)
  if (!Object.keys(changes).length) return assessmentSettings()
  if ((user.superAdmin || switchesConcurrency(changes)) && !trimmed(body.reason))
    throw assessmentReject.reasonRequired()
  const before = assessmentSettings().accreditedReviewSlaDays
  const after = writeAssessmentSettings(changes)
  if (after.accreditedReviewSlaDays !== before)
    dispatchQueuedJob(() => recomputeDueDates(after.accreditedReviewSlaDays))
  return after
}

function publishedScale() {
  const published = versionWithStatus(STATUS.PUBLISHED)
  return published ? { scaleKind: published.scaleKind } : null
}

export function assessmentSettingsResource() {
  const settings = assessmentSettings()
  return {
    accreditedReviewSlaDays: settings.accreditedReviewSlaDays,
    accreditedReviewSlaLimits: { ...SLA_LIMITS },
    uploadMaxFileMb: settings.uploadMaxFileMb,
    generalUploadMaxFileMb: settings.generalUploadMaxFileMb,
    evidenceAllowedTypes: [...settings.evidenceAllowedTypes],
    evidenceMaxFilesPerSub: settings.evidenceMaxFilesPerSub,
    evidenceTypeCatalog: [...EVIDENCE_TYPE_CATALOG],
    advancedMinEntityAgeYears: settings.advancedMinEntityAgeYears,
    advancedDisqualificationScore: settings.advancedDisqualificationScore,
    advancedInitialReviewSlaDays: settings.advancedInitialReviewSlaDays,
    committeeEntityDataVisibility: settings.committeeEntityDataVisibility,
    advancedCycleConcurrency: settings.advancedCycleConcurrency,
    runningCycles: runningCycleCounts(),
    scaleKind: settings.scaleKind,
    publishedVersion: publishedScale(),
  }
}
