import { BUILD_POSTURE } from '@/constants/buildPosture'
import { showsDemoOnlyModules } from '@/utils/build/buildPosture'

const ComingSoon = () => import('@/views/public/ComingSoon.vue')
const DROPPED_KEYS = ['beforeEnter', 'props', 'redirect']

function comingSoonRoute(route) {
  const kept = Object.fromEntries(
    Object.entries(route).filter(([key]) => !DROPPED_KEYS.includes(key)),
  )
  return { ...kept, component: ComingSoon, meta: { ...route.meta, comingSoon: true } }
}

export function withComingSoonScreens(routes, posture = BUILD_POSTURE) {
  if (showsDemoOnlyModules(posture)) return routes
  return routes.map((route) => (route.meta?.demoOnly ? comingSoonRoute(route) : route))
}
