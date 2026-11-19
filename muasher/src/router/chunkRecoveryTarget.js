import { ROUTE_NAMES } from '@/constants/routeNames'
import { isInternalPath } from '@/utils/internalPath'

const SIGN_IN_STEP_ROUTES = [ROUTE_NAMES.AUTH_MFA, ROUTE_NAMES.AUTH_OTP]

function signInRestart(to) {
  const redirect = to.query?.redirect
  return {
    name: ROUTE_NAMES.AUTH_LOGIN,
    query: isInternalPath(redirect) ? { redirect } : {},
  }
}

export function recoveryLocationFor(to) {
  return SIGN_IN_STEP_ROUTES.includes(to.name) ? signInRestart(to) : to.fullPath
}
