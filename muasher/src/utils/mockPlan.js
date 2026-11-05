import { MOCK_MODES, MOCK_MODULES } from '@/constants/mockModules'
import { BUILD_POSTURES, buildPostureOf } from '@/utils/build/buildPosture'

const KNOWN_MODULES = new Set(Object.values(MOCK_MODULES))
const LIST_SEPARATOR = ','

function listedKeys(raw) {
  return String(raw ?? '')
    .split(LIST_SEPARATOR)
    .map((key) => key.trim().toLowerCase())
    .filter(Boolean)
}

export function parseDemoModules(raw) {
  const keys = [...new Set(listedKeys(raw))]
  return {
    modules: keys.filter((key) => KNOWN_MODULES.has(key)),
    unknown: keys.filter((key) => !KNOWN_MODULES.has(key)),
  }
}

export function mockPlanOf(env = {}) {
  const posture = buildPostureOf(env)
  if (posture === BUILD_POSTURES.MOCK) return { mode: MOCK_MODES.FULL, modules: [], unknown: [] }
  if (posture === BUILD_POSTURES.REAL) return { mode: MOCK_MODES.OFF, modules: [], unknown: [] }
  const { modules, unknown } = parseDemoModules(env.VITE_DEMO_MODULES)
  const mode = modules.length ? MOCK_MODES.HYBRID : MOCK_MODES.OFF
  return { mode, modules, unknown }
}

export const mockPlan = Object.freeze(mockPlanOf(import.meta.env))

export function demoModulesIn(keys, plan = mockPlan) {
  if (plan.mode !== MOCK_MODES.HYBRID) return []
  return [...new Set(keys ?? [])].filter((key) => plan.modules.includes(key))
}

export function showsDemoData(keys, plan = mockPlan) {
  return demoModulesIn(keys, plan).length > 0
}
