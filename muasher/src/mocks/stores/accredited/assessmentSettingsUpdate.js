import { assessmentReject, fieldMessage } from '../assessments/assessmentRejection'
import { isOpen } from '../assessments/assessmentsStore'
import { dispatchQueuedJob } from '../mockQueue'
import {
  COMMITTEE_VISIBILITIES,
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
  if ('committeeEntityDataVisibility' in body) {
    if (COMMITTEE_VISIBILITIES.includes(body.committeeEntityDataVisibility))
      changes.committeeEntityDataVisibility = body.committeeEntityDataVisibility
    else errors.committeeEntityDataVisibility = [fieldMessage('validation.invalid', {}, 'settings')]
  }
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

export function updateAssessmentSettings(user, body) {
  const changes = validatedChanges(body)
  if (!Object.keys(changes).length) return assessmentSettings()
  if (user.superAdmin && !trimmed(body.reason)) throw assessmentReject.reasonRequired()
  const before = assessmentSettings().accreditedReviewSlaDays
  const after = writeAssessmentSettings(changes)
  if (after.accreditedReviewSlaDays !== before)
    dispatchQueuedJob(() => recomputeDueDates(after.accreditedReviewSlaDays))
  return after
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
  }
}
