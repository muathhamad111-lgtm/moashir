export const EVIDENCE_TYPE_CATALOG = ['pdf', 'docx', 'xlsx', 'pptx', 'jpg', 'png']
export const SLA_LIMITS = { min: 1, max: 120 }
export const UPLOAD_LIMITS = { maxFileMb: 100, maxFilesPerSub: 50 }

const DEFAULTS = {
  accreditedReviewSlaDays: 14,
  uploadMaxFileMb: 20,
  evidenceAllowedTypes: [...EVIDENCE_TYPE_CATALOG],
  evidenceMaxFilesPerSub: 10,
}

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
