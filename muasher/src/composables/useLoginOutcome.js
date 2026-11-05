import { useRoute, useRouter } from 'vue-router'
import { useAuthFlowStore } from '@/stores/authFlow'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { AUTH_FLOW_INTENTS, LOGIN_STEPS } from '@/constants/loginSteps'
import { SIGN_IN_METHODS, SIGN_IN_METHOD_QUERY } from '@/constants/signIn'
import { isInternalPath } from '@/utils/internalPath'

export function useLoginOutcome() {
  const route = useRoute()
  const router = useRouter()
  const authFlow = useAuthFlowStore()

  function redirectTarget() {
    const candidate = route.query.redirect ?? authFlow.redirect
    return isInternalPath(candidate) ? candidate : null
  }

  function redirectQuery(redirect) {
    return redirect ? { redirect } : undefined
  }

  function signInRoute(method = SIGN_IN_METHODS.PASSWORD) {
    const query = { ...redirectQuery(redirectTarget()) }
    if (method !== SIGN_IN_METHODS.PASSWORD) query[SIGN_IN_METHOD_QUERY] = method
    return { name: ROUTE_NAMES.AUTH_LOGIN, query }
  }

  function toOtp(outcome, { officialEmail, intent, identifier }, redirect) {
    authFlow.setChallenge({ challenge: outcome.otp, officialEmail, intent, identifier })
    authFlow.setPendingStep(null, redirect)
    return router.push({ name: ROUTE_NAMES.AUTH_OTP, query: redirectQuery(redirect) })
  }

  function toMfa(outcome, context, redirect) {
    authFlow.setPendingStep(outcome.step, redirect)
    return router.push({ name: ROUTE_NAMES.AUTH_MFA, query: redirectQuery(redirect) })
  }

  function toWorkspace(outcome, context, redirect) {
    const isRegistration = authFlow.intent === AUTH_FLOW_INTENTS.REGISTER
    authFlow.clear()
    if (isRegistration) {
      return router.push({ name: ROUTE_NAMES.ENTITY_PROFILE, query: { welcome: '1' } })
    }
    return router.push(redirect ?? { name: ROUTE_NAMES.APP_DASHBOARD })
  }

  const NEXT_SCREEN = {
    [LOGIN_STEPS.OTP_REQUIRED]: toOtp,
    [LOGIN_STEPS.MFA_CHALLENGE]: toMfa,
    [LOGIN_STEPS.MFA_ENROLLMENT]: toMfa,
    [LOGIN_STEPS.AUTHENTICATED]: toWorkspace,
  }

  function continueAfter(outcome, context = {}) {
    const next = NEXT_SCREEN[outcome?.step]
    if (!next) return router.push({ name: ROUTE_NAMES.AUTH_LOGIN })
    return next(outcome, context, redirectTarget())
  }

  return { continueAfter, redirectTarget, signInRoute }
}
