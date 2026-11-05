import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import process from 'node:process'
import { describe, expect, it } from 'vitest'
import { demoModulesIn, mockPlanOf, parseDemoModules, showsDemoData } from '@/utils/mockPlan'
import { MOCK_MODES, MOCK_MODULES } from '@/constants/mockModules'

function templateValue(file, name) {
  const text = readFileSync(resolve(process.cwd(), file), 'utf8')
  const line = text.split('\n').find((entry) => entry.startsWith(`${name}=`))
  return line ? line.slice(name.length + 1) : null
}

describe('mock plan', () => {
  it('keeps the whole mock layer when no API base URL is set', () => {
    expect(mockPlanOf({ VITE_DEMO_MODULES: 'bag' })).toEqual({
      mode: MOCK_MODES.FULL,
      modules: [],
      unknown: [],
    })
  })

  it('turns the mocks off for a real API without demo modules', () => {
    expect(mockPlanOf({ VITE_API_BASE_URL: '/api/v1' }).mode).toBe(MOCK_MODES.OFF)
    expect(mockPlanOf({ VITE_API_BASE_URL: '/api/v1', VITE_DEMO_MODULES: ' , ' }).mode).toBe(
      MOCK_MODES.OFF,
    )
  })

  it('starts hybrid mode with the listed modules only, normalised and deduplicated', () => {
    const plan = mockPlanOf({
      VITE_API_BASE_URL: '/api/v1',
      VITE_DEMO_MODULES: ' Bag, kpis ,bag,unknown-module',
    })
    expect(plan).toEqual({
      mode: MOCK_MODES.HYBRID,
      modules: ['bag', 'kpis'],
      unknown: ['unknown-module'],
    })
  })

  it('ignores a list made of unknown keys only', () => {
    expect(mockPlanOf({ VITE_API_BASE_URL: '/api/v1', VITE_DEMO_MODULES: 'lenses' })).toEqual({
      mode: MOCK_MODES.OFF,
      modules: [],
      unknown: ['lenses'],
    })
  })

  it('reports demo data only for listed modules in hybrid mode', () => {
    const hybrid = mockPlanOf({ VITE_API_BASE_URL: '/api/v1', VITE_DEMO_MODULES: 'cms,kpis' })
    expect(demoModulesIn(['kpis', 'criteria'], hybrid)).toEqual(['kpis'])
    expect(showsDemoData(['criteria'], hybrid)).toBe(false)
    expect(showsDemoData(undefined, hybrid)).toBe(false)
    expect(showsDemoData(['kpis'], mockPlanOf({}))).toBe(false)
  })

  it.each(['.env.staging.example', '.env.production.example'])(
    '%s lists known modules, a same-origin API and no demo login',
    (file) => {
      const { modules, unknown } = parseDemoModules(templateValue(file, 'VITE_DEMO_MODULES'))
      expect(unknown).toEqual([])
      expect(modules.length).toBeGreaterThan(0)
      expect(modules).not.toContain(MOCK_MODULES.CRITERIA)
      expect(templateValue(file, 'VITE_API_BASE_URL')).toBe('/api/v1')
      expect(templateValue(file, 'VITE_DEMO_LOGIN')).toBe('false')
      expect(templateValue(file, 'VITE_SENTRY_DSN')).toBe('')
    },
  )

  it('uses the same demo list for staging and production', () => {
    expect(templateValue('.env.staging.example', 'VITE_DEMO_MODULES')).toBe(
      templateValue('.env.production.example', 'VITE_DEMO_MODULES'),
    )
  })
})
