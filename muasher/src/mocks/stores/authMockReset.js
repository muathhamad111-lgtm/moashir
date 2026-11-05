import { resetAuthSettings } from './authSettingsStore'
import { resetSignInLockout } from './signInLockout'

export function resetAuthMock() {
  resetAuthSettings()
  resetSignInLockout()
}
