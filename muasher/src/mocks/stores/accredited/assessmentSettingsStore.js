export const EVIDENCE_TYPE_CATALOG = ['pdf', 'docx', 'xlsx', 'pptx', 'jpg', 'png']
export const SLA_LIMITS = { min: 1, max: 120 }
export const UPLOAD_LIMITS = { maxFileMb: 100, maxFilesPerSub: 50 }

const DEFAULTS = {
  accreditedReviewSlaDays: 14,
  uploadMaxFileMb: 20,
  generalUploadMaxFileMb: 20,
  evidenceAllowedTypes: [...EVIDENCE_TYPE_CATALOG],
  evidenceMaxFilesPerSub: 10,
  advancedMinEntityAgeYears: 3,
  advancedDisqualificationScore: '60.00',
  advancedInitialReviewSlaDays: 14,
  committeeEntityDataVisibility: 'name_only',
}

export const COMMITTEE_VISIBILITIES = ['name_only', 'code_only', 'full']

let settings = { ...DEFAULTS, evidenceAllowedTypes: [...DEFAULTS.evidenceAllowedTypes] }

export function resetAssessmentSettings() {
  settings = { ...DEFAULTS, evidenceAllowedTypes: [...DEFAULTS.evidenceAllowedTypes] }
}

export function assessmentSettings() {
  return settings
}

export function writeAssessmentSettings(changes) {
  settings = { ...settings, ...changes }
  return settings
}

export function uploadLimits() {
  return {
    maxFileMb: settings.uploadMaxFileMb,
    allowedTypes: [...settings.evidenceAllowedTypes],
    maxFilesPerSub: settings.evidenceMaxFilesPerSub,
  }
}

export const ADVANCED_UPLOAD_DEFAULTS = Object.freeze({
  maxFileMb: 20,
  allowedTypes: ['pdf', 'docx', 'xlsx'],
  maxFilesPerSub: 5,
})
