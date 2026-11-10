import { describe, expect, it } from 'vitest'
import {
  BUILD_POSTURES,
  buildPostureOf,
  shipsMockLayer,
  showsDemoOnlyModules,
  showsModulesAwaitingBackend,
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

  it('ships the mock layer only in the full mock build', () => {
    expect(shipsMockLayer(BUILD_POSTURES.MOCK)).toBe(true)
    expect(shipsMockLayer(BUILD_POSTURES.DEMO)).toBe(false)
    expect(shipsMockLayer(BUILD_POSTURES.REAL)).toBe(false)
  })

  it('shows the modules awaiting their backend only on the mock layer', () => {
    expect(showsModulesAwaitingBackend(BUILD_POSTURES.MOCK)).toBe(true)
    expect(showsModulesAwaitingBackend(BUILD_POSTURES.DEMO)).toBe(false)
    expect(showsModulesAwaitingBackend(BUILD_POSTURES.REAL)).toBe(false)
  })

  it('shows the demo-only modules everywhere except in a real-operation build', () => {
    expect(showsDemoOnlyModules(BUILD_POSTURES.MOCK)).toBe(true)
    expect(showsDemoOnlyModules(BUILD_POSTURES.DEMO)).toBe(true)
    expect(showsDemoOnlyModules(BUILD_POSTURES.REAL)).toBe(false)
  })
})
