import { assessmentSettings } from '../accredited/assessmentSettingsStore'

export function cycleDefaults() {
  const settings = assessmentSettings()
  return {
    minEntityAgeYears: settings.advancedMinEntityAgeYears,
    disqualificationScore: settings.advancedDisqualificationScore,
    initialReviewSlaDays: settings.advancedInitialReviewSlaDays,
  }
}

export function cycleDefaultsResource() {
  return cycleDefaults()
}
