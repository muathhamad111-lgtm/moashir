import { assessmentSettings } from '../accredited/assessmentSettingsStore'
import { priceInForce } from '../finance/priceVersions'

const FREE_FEE = '0.00'

export function cycleDefaults() {
  const settings = assessmentSettings()
  return {
    minEntityAgeYears: settings.advancedMinEntityAgeYears,
    disqualificationScore: settings.advancedDisqualificationScore,
    initialReviewSlaDays: settings.advancedInitialReviewSlaDays,
  }
}

export function defaultEntryFee() {
  return priceInForce('award') ?? FREE_FEE
}

export function cycleDefaultsResource() {
  return { ...cycleDefaults(), entryFee: defaultEntryFee() }
}
