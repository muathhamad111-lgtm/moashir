export function scrollBehavior(to, from) {
  if (to.path !== from.path) return { top: 0 }
  if (to.hash || to.meta?.keepsScrollOnQuery) return false
  return { top: 0 }
}
