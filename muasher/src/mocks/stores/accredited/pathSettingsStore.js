import { PATH_GUIDES_FIXTURE } from '../../fixtures/pathGuides'
import { pathLabelOf } from '../../fixtures/pathLabels'
import { priceInForce, quoteOfPrice } from '../finance/priceVersions'
import { pathIdentityOf, resetPathIdentities, writePathIdentity } from '../paths/pathIdentityStore'
import {
  ADVANCED_UPLOAD_DEFAULTS,
  EVIDENCE_TYPE_CATALOG,
  SLA_LIMITS,
  UPLOAD_LIMITS,
  assessmentSettings,
  uploadLimits,
  writeAssessmentSettings,
} from './assessmentSettingsStore'

export const SETTINGS_PATHS = ['self', 'accredited', 'advanced']
export const REVIEWED_SETTINGS_PATHS = ['accredited', 'advanced']
const SELF_PATH = 'self'
export const TEXT_KEYS = ['intro', 'instructions', 'afterSubmission']
const PAID_PATH = 'accredited'
const SLA_KEYS = { accredited: 'accreditedReviewSlaDays', advanced: 'advancedInitialReviewSlaDays' }

let advancedUploads = null
let texts = null

function copyTexts(source) {
  return Object.fromEntries(
    TEXT_KEYS.map((key) => [key, source?.[key] ? { ...source[key] } : null]),
  )
}

export function resetPathSettings() {
  resetPathIdentities()
  advancedUploads = {
    ...ADVANCED_UPLOAD_DEFAULTS,
    allowedTypes: [...ADVANCED_UPLOAD_DEFAULTS.allowedTypes],
  }
  texts = Object.fromEntries(
    REVIEWED_SETTINGS_PATHS.map((path) => [path, copyTexts(PATH_GUIDES_FIXTURE[path].entityTexts)]),
  )
}

resetPathSettings()

function uploadsOf(path) {
  return path === PAID_PATH
    ? uploadLimits()
    : { ...advancedUploads, allowedTypes: [...advancedUploads.allowedTypes] }
}

export function priceQuoteOf(path) {
  if (path !== PAID_PATH) return null
  const price = priceInForce(PAID_PATH)
  return price && Number(price) > 0 ? quoteOfPrice(price) : null
}

export function pathTextsOf(path) {
  return copyTexts(texts[path])
}

export function pathSettingsResource(path) {
  const identity = {
    path,
    pathLabel: pathLabelOf(path),
    description: pathIdentityOf(path).description,
  }
  if (path === SELF_PATH) return identity
  return {
    ...identity,
    reviewSlaDays: assessmentSettings()[SLA_KEYS[path]],
    reviewSlaLimits: { ...SLA_LIMITS },
    uploadLimits: uploadsOf(path),
    uploadLimitBounds: { ...UPLOAD_LIMITS },
    evidenceTypeCatalog: [...EVIDENCE_TYPE_CATALOG],
    isPaid: priceQuoteOf(path) !== null,
    price: priceQuoteOf(path),
    entityTexts: pathTextsOf(path),
  }
}

export function writePathSettings(path, changes) {
  writePathIdentity(path, changes)
  if (path === SELF_PATH) return
  if ('reviewSlaDays' in changes)
    writeAssessmentSettings({ [SLA_KEYS[path]]: changes.reviewSlaDays })
  const uploads = {
    ...('uploadMaxFileMb' in changes ? { maxFileMb: changes.uploadMaxFileMb } : {}),
    ...('evidenceAllowedTypes' in changes ? { allowedTypes: changes.evidenceAllowedTypes } : {}),
    ...('evidenceMaxFilesPerSub' in changes
      ? { maxFilesPerSub: changes.evidenceMaxFilesPerSub }
      : {}),
  }
  if (path === PAID_PATH) {
    writeAssessmentSettings({
      ...('maxFileMb' in uploads ? { uploadMaxFileMb: uploads.maxFileMb } : {}),
      ...('allowedTypes' in uploads ? { evidenceAllowedTypes: uploads.allowedTypes } : {}),
      ...('maxFilesPerSub' in uploads ? { evidenceMaxFilesPerSub: uploads.maxFilesPerSub } : {}),
    })
  } else advancedUploads = { ...advancedUploads, ...uploads }
  Object.entries(changes.entityTexts ?? {}).forEach(([key, value]) => {
    texts[path][key] = value ? { ...value } : null
  })
}
