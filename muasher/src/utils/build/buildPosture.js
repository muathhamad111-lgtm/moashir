export const BUILD_POSTURES = Object.freeze({ MOCK: 'mock', DEMO: 'demo', REAL: 'real' })

const DEMO_FLAG = 'true'

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

export function shipsMockLayer(posture) {
  return posture === BUILD_POSTURES.MOCK
}

export function showsModulesAwaitingBackend(posture) {
  return posture === BUILD_POSTURES.MOCK
}

export function showsDemoOnlyModules(posture) {
  return posture !== BUILD_POSTURES.REAL
}
