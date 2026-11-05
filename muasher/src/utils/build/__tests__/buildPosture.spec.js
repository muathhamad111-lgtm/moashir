import { describe, expect, it } from 'vitest'
import {
  BUILD_POSTURES,
  BUILD_POSTURE_PROBLEMS,
  buildPostureOf,
  buildPostureProblemOf,
  listedDemoModules,
  showsDemoOnlyModules,
} from '@/utils/build/buildPosture'

const API = { VITE_API_BASE_URL: '/api/v1' }

describe('build posture', () => {
  it('is the full mock layer without an API base URL', () => {
    expect(buildPostureOf({})).toBe(BUILD_POSTURES.MOCK)
    expect(buildPostureOf({ VITE_DEMO_BUILD: 'true' })).toBe(BUILD_POSTURES.MOCK)
  })

  it('is a client demo only with the explicit flag', () => {
    expect(buildPostureOf({ ...API, VITE_DEMO_BUILD: 'true' })).toBe(BUILD_POSTURES.DEMO)
    expect(buildPostureOf({ ...API, VITE_DEMO_BUILD: ' TRUE ' })).toBe(BUILD_POSTURES.DEMO)
  })

  it('defaults to real operation for any other flag value', () => {
    expect(buildPostureOf(API)).toBe(BUILD_POSTURES.REAL)
    expect(buildPostureOf({ ...API, VITE_DEMO_BUILD: '' })).toBe(BUILD_POSTURES.REAL)
    expect(buildPostureOf({ ...API, VITE_DEMO_BUILD: 'false' })).toBe(BUILD_POSTURES.REAL)
    expect(buildPostureOf({ ...API, VITE_DEMO_BUILD: '1' })).toBe(BUILD_POSTURES.REAL)
  })

  it('refuses demo modules in a real-operation build only', () => {
    expect(buildPostureProblemOf({ ...API, VITE_DEMO_MODULES: 'bag' })).toBe(
      BUILD_POSTURE_PROBLEMS.DEMO_MODULES_IN_REAL_BUILD,
    )
    expect(buildPostureProblemOf({ ...API, VITE_DEMO_MODULES: ' , ' })).toBeNull()
    expect(
      buildPostureProblemOf({ ...API, VITE_DEMO_BUILD: 'true', VITE_DEMO_MODULES: 'bag' }),
    ).toBeNull()
    expect(buildPostureProblemOf({ VITE_DEMO_MODULES: 'bag' })).toBeNull()
  })

  it('lists the configured demo modules', () => {
    expect(listedDemoModules({ VITE_DEMO_MODULES: ' bag, ai ,,' })).toEqual(['bag', 'ai'])
    expect(listedDemoModules({})).toEqual([])
  })

  it('shows the demo-only modules everywhere except in a real-operation build', () => {
    expect(showsDemoOnlyModules(BUILD_POSTURES.MOCK)).toBe(true)
    expect(showsDemoOnlyModules(BUILD_POSTURES.DEMO)).toBe(true)
    expect(showsDemoOnlyModules(BUILD_POSTURES.REAL)).toBe(false)
  })
})
