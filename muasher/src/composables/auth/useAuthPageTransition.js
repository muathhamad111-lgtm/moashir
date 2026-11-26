import { watch } from 'vue'
import { useRoute } from 'vue-router'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { AUTH_MOTION, slideDirection, slideIn, slideOut } from '@/composables/auth/authSlide'
import { focusArrival, shouldMoveFocus } from '@/composables/auth/authFocus'

// Depth along the sign-in journey: a deeper page slides in ahead, a shallower one from behind.
export const AUTH_SWITCH_STEPS = Object.freeze({
  [ROUTE_NAMES.AUTH_LOGIN]: 0,
  [ROUTE_NAMES.AUTH_REGISTER]: 1,
  [ROUTE_NAMES.AUTH_FORGOT_PASSWORD]: 1,
  [ROUTE_NAMES.AUTH_OTP]: 1,
  [ROUTE_NAMES.AUTH_MFA]: 1,
})

function depthOf(name) {
  return Object.hasOwn(AUTH_SWITCH_STEPS, name) ? AUTH_SWITCH_STEPS[name] : -1
}

export function authSwitchStep(from, to) {
  return slideDirection(depthOf(from), depthOf(to))
}

export function useAuthPageTransition(viewport) {
  const route = useRoute()
  let step = 0
  let hadFocus = false

  watch(
    () => route.name,
    (to, from) => {
      step = authSwitchStep(from, to)
    },
    { flush: 'sync' },
  )

  function onBeforeLeave(page) {
    hadFocus = page.contains(document.activeElement)
    if (step) viewport.settle(AUTH_MOTION.leaveMs)
  }

  function onLeave(page, done) {
    slideOut(page, step, done)
  }

  function onEnter(page, done) {
    if (shouldMoveFocus(page, hadFocus)) focusArrival(page)
    if (step) viewport.followNow()
    slideIn(page, step, done)
  }

  return { onBeforeLeave, onLeave, onEnter }
}
