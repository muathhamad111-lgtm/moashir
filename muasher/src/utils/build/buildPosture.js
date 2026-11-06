export const BUILD_POSTURES = Object.freeze({ MOCK: 'mock', DEMO: 'demo', REAL: 'real' })

export const BUILD_POSTURE_PROBLEMS = Object.freeze({
  DEMO_MODULES_IN_REAL_BUILD: 'demo_modules_in_real_build',
})

const DEMO_FLAG = 'true'
const LIST_SEPARATOR = ','

function isDemoFlagOn(value) {
  return (
    String(value ?? '')
      .trim()
      .toLowerCase() === DEMO_FLAG
  )
}

export function buildPostureOf(env = {}) {
  if (!env.VITE_API_BASE_URL) return BUILD_POSTURES.MOCK
  return isDemoFlagOn(env.VITE_DEMO_BUILD) ? BUILD_POSTURES.DEMO : BUILD_POSTURES.REAL
}

export function listedDemoModules(env = {}) {
  return String(env.VITE_DEMO_MODULES ?? '')
    .split(LIST_SEPARATOR)
    .map((key) => key.trim())
    .filter(Boolean)
}

export function buildPostureProblemOf(env = {}) {
  if (buildPostureOf(env) !== BUILD_POSTURES.REAL) return null
  return listedDemoModules(env).length ? BUILD_POSTURE_PROBLEMS.DEMO_MODULES_IN_REAL_BUILD : null
}

export function showsDemoOnlyModules(posture) {
  return posture !== BUILD_POSTURES.REAL
}
