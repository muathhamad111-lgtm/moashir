const DEFAULTS = Object.freeze({ entitySecondFactorEnabled: true })

let settings = { ...DEFAULTS }

export function resetAuthSettings() {
  settings = { ...DEFAULTS }
}

export function entitySecondFactorEnabled() {
  return settings.entitySecondFactorEnabled
}

export function authSettingsResource() {
  return {
    entitySecondFactorEnabled: settings.entitySecondFactorEnabled,
    entitySecondFactorChannel: 'email',
    staffTotpRequired: true,
  }
}

export function setEntitySecondFactor(enabled) {
  const changed = settings.entitySecondFactorEnabled !== enabled
  settings = { ...settings, entitySecondFactorEnabled: enabled }
  return changed
}
