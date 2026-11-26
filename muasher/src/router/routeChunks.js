let warming = 0

function isLazyComponent(component) {
  return typeof component === 'function' && !('__vccOpts' in component)
}

function warm(component) {
  warming += 1
  return Promise.resolve()
    .then(component)
    .catch(() => {})
    .finally(() => {
      warming -= 1
    })
}

export function isWarmingRouteChunks() {
  return warming > 0
}

export function warmRouteChunks(router, location) {
  for (const record of router.resolve(location).matched) {
    const component = record.components?.default
    if (isLazyComponent(component)) warm(component)
  }
}
