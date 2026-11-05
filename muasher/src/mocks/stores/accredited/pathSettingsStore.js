import { PATH_GUIDES_FIXTURE } from '../../fixtures/pathGuides'
import { pathLabelOf } from '../../fixtures/pathLabels'
import { financeState, persistFinanceState } from '../finance/financeState'
import { quoteOf } from '../finance/financeMoney'
import {
  ADVANCED_UPLOAD_DEFAULTS,
  EVIDENCE_TYPE_CATALOG,
  SLA_LIMITS,
  UPLOAD_LIMITS,
  assessmentSettings,
  uploadLimits,
  writeAssessmentSettings,
} from './assessmentSettingsStore'

export const SETTINGS_PATHS = ['accredited', 'advanced']
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
  advancedUploads = {
    ...ADVANCED_UPLOAD_DEFAULTS,
    allowedTypes: [...ADVANCED_UPLOAD_DEFAULTS.allowedTypes],
  }
  texts = Object.fromEntries(
    SETTINGS_PATHS.map((path) => [path, copyTexts(PATH_GUIDES_FIXTURE[path].entityTexts)]),
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
  const { accreditedPrice, vatRate } = financeState().settings
  return accreditedPrice ? quoteOf(accreditedPrice, vatRate) : null
}

export function pathTextsOf(path) {
  return copyTexts(texts[path])
}

export function pathSettingsResource(path) {
  return {
    path,
    pathLabel: pathLabelOf(path),
    reviewSlaDays: assessmentSettings()[SLA_KEYS[path]],
    reviewSlaLimits: { ...SLA_LIMITS },
    uploadLimits: uploadsOf(path),
    uploadLimitBounds: { ...UPLOAD_LIMITS },
    evidenceTypeCatalog: [...EVIDENCE_TYPE_CATALOG],
    isPaid: path === PAID_PATH,
    price: priceQuoteOf(path),
    entityTexts: pathTextsOf(path),
  }
}

export function writePathSettings(path, changes) {
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
  if (changes.price) {
    financeState().settings.accreditedPrice = changes.price
    persistFinanceState()
  }
  Object.entries(changes.entityTexts ?? {}).forEach(([key, value]) => {
    texts[path][key] = value ? { ...value } : null
  })
}
