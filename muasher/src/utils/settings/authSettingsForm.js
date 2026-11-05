import { boolean, object } from 'yup'

export function authSettingsSchema() {
  return object({ entitySecondFactorEnabled: boolean() })
}

export function authSettingsValues(settings) {
  return { entitySecondFactorEnabled: settings.entitySecondFactorEnabled === true }
}

export function changedAuthSettings(settings, values) {
  return values.entitySecondFactorEnabled === settings.entitySecondFactorEnabled
    ? {}
    : { entitySecondFactorEnabled: values.entitySecondFactorEnabled === true }
}

export function turnsSecondFactorOff(settings, values) {
  return settings?.entitySecondFactorEnabled === true && values.entitySecondFactorEnabled === false
}
