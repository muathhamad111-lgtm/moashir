import { BUILD_POSTURE } from '@/constants/buildPosture'
import { showsDemoOnlyModules, showsModulesAwaitingBackend } from '@/utils/build/buildPosture'

const ComingSoon = () => import('@/views/public/ComingSoon.vue')
const DROPPED_KEYS = ['beforeEnter', 'props', 'redirect']

function comingSoonRoute(route) {
  const kept = Object.fromEntries(
    Object.entries(route).filter(([key]) => !DROPPED_KEYS.includes(key)),
  )
  return { ...kept, component: ComingSoon, meta: { ...route.meta, comingSoon: true } }
}

function landsOnComingSoon(meta, posture) {
  if (meta?.awaitsBackend) return !showsModulesAwaitingBackend(posture)
  if (meta?.demoOnly) return !showsDemoOnlyModules(posture)
  return false
}

export function withComingSoonScreens(routes, posture = BUILD_POSTURE) {
  return routes.map((route) =>
    landsOnComingSoon(route.meta, posture) ? comingSoonRoute(route) : route,
  )
}
