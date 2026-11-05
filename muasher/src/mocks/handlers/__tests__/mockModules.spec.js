import { describe, expect, it } from 'vitest'
import { CORE_MOCK_KEYS, handlers, handlersOfModules, mockModules } from '@/mocks/handlers'
import { bagHandlers } from '@/mocks/handlers/bag'
import { bagRequestsHandlers } from '@/mocks/handlers/bagRequests'
import { MOCK_MODULES } from '@/constants/mockModules'

const BOARD_BRIDGE_ROUTE = 'GET /api/v1/dashboard/summary'
const CERTIFICATE_LIST_ROUTE = 'GET /api/v1/certificates'

function headersOf(list) {
  return list.map((handler) => handler.info.header)
}

describe('mock module registry', () => {
  it('names every switchable module exactly once and keeps the core apart', () => {
    const keys = mockModules.filter((module) => !module.core).map((module) => module.key)
    expect([...keys].sort()).toEqual(Object.values(MOCK_MODULES).sort())
    expect(mockModules.filter((module) => module.core).map((module) => module.key)).toEqual(
      Object.values(CORE_MOCK_KEYS),
    )
  })

  it('builds the full mock layer from every module without a duplicated route', () => {
    const all = headersOf(handlers)
    expect(all.length).toBe(
      mockModules.reduce((total, module) => total + module.handlers.length, 0),
    )
    const duplicated = all.filter((header, index) => all.indexOf(header) !== index)
    expect(duplicated.sort()).toEqual([BOARD_BRIDGE_ROUTE, CERTIFICATE_LIST_ROUTE].sort())
  })

  it('splits the dashboard summary by board between the legacy and the entity board mocks', () => {
    const owners = mockModules
      .filter((module) => headersOf(module.handlers).includes(BOARD_BRIDGE_ROUTE))
      .map((module) => module.key)
    expect(owners.sort()).toEqual([MOCK_MODULES.DASHBOARDS, MOCK_MODULES.ENTITY_BOARD].sort())
  })

  it('serves the staff certificate list of a request before the owner list, in one module', () => {
    const owners = mockModules
      .filter((module) => headersOf(module.handlers).includes(CERTIFICATE_LIST_ROUTE))
      .map((module) => module.key)
    expect(owners).toEqual([MOCK_MODULES.CERTIFICATES])
  })

  it('selects only the handlers of the listed demo modules', () => {
    expect(headersOf(handlersOfModules([MOCK_MODULES.BAG]))).toEqual(
      headersOf([...bagHandlers, ...bagRequestsHandlers]),
    )
    expect(handlersOfModules([])).toEqual([])
  })

  it('never serves the session or sign-in mocks in hybrid mode', () => {
    const selected = headersOf(
      handlersOfModules([...Object.values(CORE_MOCK_KEYS), MOCK_MODULES.KPIS]),
    )
    expect(
      selected.some((header) => header.includes('/sanctum/') || header.includes('/auth/')),
    ).toBe(false)
    expect(selected.length).toBeGreaterThan(0)
  })

  it('keeps the public model criteria with the criteria module, not the CMS', () => {
    const cms = headersOf(handlersOfModules([MOCK_MODULES.CMS]))
    const criteria = headersOf(handlersOfModules([MOCK_MODULES.CRITERIA]))
    expect(cms).not.toContain('GET /api/v1/content/model-criteria')
    expect(criteria).toContain('GET /api/v1/content/model-criteria')
  })

  it('splits the legacy committee mocks between committee and secretary', () => {
    const secretary = headersOf(handlersOfModules([MOCK_MODULES.SECRETARY]))
    const committee = headersOf(handlersOfModules([MOCK_MODULES.COMMITTEE]))
    expect(secretary.every((header) => /\/(secretary|averages)\//.test(header))).toBe(true)
    expect(committee.some((header) => /\/(secretary|averages)\//.test(header))).toBe(false)
  })
})
