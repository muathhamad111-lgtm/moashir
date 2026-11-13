export function scrollBehavior(to, from) {
  if (to.hash && to.path === from.path) return false
  return { top: 0 }
}
