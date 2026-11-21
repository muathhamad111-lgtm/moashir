import { resetAuthSettings } from './authSettingsStore'
import { resetIdentityStore } from './identityStore'
import { resetPasswordResetLinks } from './passwordResetLinks'
import { resetSignInCodeRequests } from './signInCodeRequests'
import { resetSignInSmsCap } from './signInCodeChannel'
import { resetSignInLockout } from './signInLockout'

export function resetAuthMock() {
  resetAuthSettings()
  resetIdentityStore()
  resetPasswordResetLinks()
  resetSignInLockout()
  resetSignInCodeRequests()
  resetSignInSmsCap()
}
