import { assessmentReject, fieldMessage } from './assessments/assessmentRejection'
import { reasonError, throwIfErrors, trimmed } from './accredited/accreditedValidation'

export const LEVEL_ORDER = ['bronze', 'silver', 'gold', 'diamond']
const DEFAULTS = {
  accreditedIssuanceEnabled: true,
  levelThresholds: { bronze: '60.00', silver: '70.00', gold: '80.00', diamond: '90.00' },
}
const PERCENT = /^\d{1,3}(\.\d{1,2})?$/

let settings = null

export function resetCertificateSettings() {
  settings = { ...DEFAULTS, levelThresholds: { ...DEFAULTS.levelThresholds } }
}

resetCertificateSettings()

export function certificateSettingsResource() {
  return {
    accreditedIssuanceEnabled: settings.accreditedIssuanceEnabled,
    levelThresholds: { ...settings.levelThresholds },
  }
}

function percentValid(value) {
  return (
    typeof value === 'string' && PERCENT.test(value) && Number(value) > 0 && Number(value) <= 100
  )
}

function thresholdErrors(thresholds, errors) {
  if (typeof thresholds !== 'object' || thresholds === null) {
    errors.levelThresholds = [fieldMessage('validation.invalid', {}, 'settings')]
    return
  }
  LEVEL_ORDER.filter((level) => !percentValid(thresholds[level])).forEach((level) => {
    errors[`levelThresholds.${level}`] = [
      fieldMessage('validation.between', { min: 0, max: 100 }, 'settings'),
    ]
  })
  if (Object.keys(errors).length) return
  const values = LEVEL_ORDER.map((level) => Number(thresholds[level]))
  if (values.some((value, index) => index > 0 && value <= values[index - 1]))
    errors.levelThresholds = [fieldMessage('level_thresholds.ascending')]
}

function validated(body) {
  const errors = {}
  if ('accreditedIssuanceEnabled' in body && typeof body.accreditedIssuanceEnabled !== 'boolean')
    errors.accreditedIssuanceEnabled = [fieldMessage('validation.boolean', {}, 'settings')]
  if ('levelThresholds' in body) thresholdErrors(body.levelThresholds, errors)
  const reason = reasonError(body.reason)
  if (reason) errors.reason = reason
  throwIfErrors(errors)
}

export function updateCertificateSettings(user, body) {
  validated(body)
  const changes = Object.keys(body).filter((key) => key !== 'reason')
  if (!changes.length) return
  if (user.superAdmin && !trimmed(body.reason)) throw assessmentReject.reasonRequired()
  if ('accreditedIssuanceEnabled' in body)
    settings.accreditedIssuanceEnabled = body.accreditedIssuanceEnabled
  if ('levelThresholds' in body)
    settings.levelThresholds = Object.fromEntries(
      LEVEL_ORDER.map((level) => [level, Number(body.levelThresholds[level]).toFixed(2)]),
    )
}
