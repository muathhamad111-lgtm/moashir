import { MOCK_MODES, MOCK_MODULES } from '@/constants/mockModules'

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
  if (!env.VITE_API_BASE_URL) return { mode: MOCK_MODES.FULL, modules: [], unknown: [] }
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
