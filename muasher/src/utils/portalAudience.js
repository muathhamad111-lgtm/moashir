import { ROLES } from '@/constants/roles'
import {
  PORTAL_AREA_ROUTES,
  PORTAL_MOBILE_TASK_ROUTES,
  PORTAL_TASK_ROUTES,
} from '@/constants/portal'

const APP_LAYOUT = 'app'
const PORTAL_LAYOUT = 'portal'

export function isPortalAudience(roles = []) {
  return roles.length > 0 && roles.every((role) => role === ROLES.ENTITY)
}

export function layoutKeyFor(routeLayout, roles = []) {
  return routeLayout === APP_LAYOUT && isPortalAudience(roles) ? PORTAL_LAYOUT : routeLayout
}

export function portalAreaOf(routeName) {
  const entry = Object.entries(PORTAL_AREA_ROUTES).find(([, names]) => names.includes(routeName))
  return entry?.[0] ?? null
}

export function isTaskScreen(routeName) {
  return PORTAL_TASK_ROUTES.includes(routeName)
}

export function isMobileTaskScreen(routeName) {
  return isTaskScreen(routeName) || PORTAL_MOBILE_TASK_ROUTES.includes(routeName)
}
