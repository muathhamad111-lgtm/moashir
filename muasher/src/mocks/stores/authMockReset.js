import { resetAuthSettings } from './authSettingsStore'
import { resetSignInCodeRequests } from './signInCodeRequests'
import { resetSignInSmsCap } from './signInCodeChannel'
import { resetSignInLockout } from './signInLockout'

export function resetAuthMock() {
  resetAuthSettings()
  resetSignInLockout()
  resetSignInCodeRequests()
  resetSignInSmsCap()
}
