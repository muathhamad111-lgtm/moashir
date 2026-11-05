import { describe, expect, it } from 'vitest'
import { routes } from '@/router/routes'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { MOCK_MODULES } from '@/constants/mockModules'

const KNOWN = new Set(Object.values(MOCK_MODULES))

function metaOf(name) {
  return routes.find((route) => route.name === name)?.meta ?? {}
}

describe('demo module markers on routes', () => {
  it('uses known module keys only', () => {
    const listed = routes.flatMap((route) => route.meta?.demoModules ?? [])
    expect(listed.length).toBeGreaterThan(0)
    expect(listed.every((key) => KNOWN.has(key))).toBe(true)
  })

  it('marks the screens of modules without a backend', () => {
    expect(metaOf(ROUTE_NAMES.BAG_INDEX).demoModules).toEqual([MOCK_MODULES.BAG])
    expect(metaOf(ROUTE_NAMES.ASSESSMENT_ADVANCED_RESULT).demoModules).toBeUndefined()
  })

  it('leaves the connected screens unmarked', () => {
    ;[
      ROUTE_NAMES.ADMIN_CRITERIA,
      ROUTE_NAMES.APP_DASHBOARD,
      ROUTE_NAMES.ADMIN_SETTINGS_NOTIFICATIONS,
      ROUTE_NAMES.ENTITY_PROFILE,
      ROUTE_NAMES.ASSESSMENT_SELF,
      ROUTE_NAMES.ASSESSMENT_ACCREDITED_PAY,
      ROUTE_NAMES.AWARD_CYCLES,
      ROUTE_NAMES.ADMIN_SETTINGS_INTEGRATIONS,
      ROUTE_NAMES.REQUESTS_INDEX,
      ROUTE_NAMES.ADMIN_USERS,
      ROUTE_NAMES.AWARD_COMMITTEE_ROSTER,
      ROUTE_NAMES.AWARD_COMMITTEE,
      ROUTE_NAMES.AWARD_SECRETARY_DESK,
      ROUTE_NAMES.AWARD_RESULTS_BOARD,
      ROUTE_NAMES.AWARD_NATIONAL,
    ].forEach((name) => expect(metaOf(name).demoModules).toBeUndefined())
  })
})
