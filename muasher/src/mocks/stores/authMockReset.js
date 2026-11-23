import { resetAuthSettings } from './authSettingsStore'
import { resetIdentityStore } from './identityStore'
import { resetNdaStore } from './ndaStore'
import { resetPasswordResetLinks } from './passwordResetLinks'
import { resetSignInCodeRequests } from './signInCodeRequests'
import { resetSignInSmsCap } from './signInCodeChannel'
import { resetSignInLockout } from './signInLockout'

export function resetAuthMock() {
  resetAuthSettings()
  resetIdentityStore()
  resetNdaStore()
  resetPasswordResetLinks()
  resetSignInLockout()
  resetSignInCodeRequests()
  resetSignInSmsCap()
}
