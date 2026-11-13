import { describe, expect, it } from 'vitest'
import { handlers } from '@/mocks/handlers'
import { dashboardHandlers } from '@/mocks/handlers/dashboard'
import { entityBoardHandlers } from '@/mocks/handlers/entityBoard'
import { certificatesHandlers } from '@/mocks/handlers/certificates'
import { certificatesStaffHandlers } from '@/mocks/handlers/certificatesStaff'
import { committeeHandlers } from '@/mocks/handlers/committee'
import { secretaryHandlers } from '@/mocks/handlers/secretary'
import { contentHandlers } from '@/mocks/handlers/content'
import { criteriaHandlers } from '@/mocks/handlers/criteria'

const BOARD_BRIDGE_ROUTE = 'GET /api/v1/dashboard/summary'
const CERTIFICATE_LIST_ROUTE = 'GET /api/v1/certificates'

function headersOf(list) {
  return list.map((handler) => handler.info.header)
}

describe('mock handler registry', () => {
  it('serves every route once, apart from the two intended bridges', () => {
    const all = headersOf(handlers)
    const duplicated = all.filter((header, index) => all.indexOf(header) !== index)
    expect(duplicated.sort()).toEqual([BOARD_BRIDGE_ROUTE, CERTIFICATE_LIST_ROUTE].sort())
  })

  it('splits the dashboard summary by board between the legacy and the entity board mocks', () => {
    expect(headersOf(dashboardHandlers)).toContain(BOARD_BRIDGE_ROUTE)
    expect(headersOf(entityBoardHandlers)).toContain(BOARD_BRIDGE_ROUTE)
  })

  it('serves the staff certificate list of a request before the owner list', () => {
    const staff = handlers.indexOf(
      certificatesStaffHandlers.find((handler) => handler.info.header === CERTIFICATE_LIST_ROUTE),
    )
    const owner = handlers.indexOf(
      certificatesHandlers.find((handler) => handler.info.header === CERTIFICATE_LIST_ROUTE),
    )
    expect(staff).toBeGreaterThanOrEqual(0)
    expect(owner).toBeGreaterThan(staff)
  })

  it('keeps the public model criteria with the criteria mocks, not the CMS', () => {
    expect(headersOf(contentHandlers)).not.toContain('GET /api/v1/content/model-criteria')
    expect(headersOf(criteriaHandlers)).toContain('GET /api/v1/content/model-criteria')
  })

  it('splits the legacy committee mocks between committee and secretary', () => {
    const secretary = headersOf(secretaryHandlers)
    const committee = headersOf(committeeHandlers)
    expect(secretary.every((header) => /\/(secretary|averages)\//.test(header))).toBe(true)
    expect(committee.some((header) => /\/(secretary|averages)\//.test(header))).toBe(false)
  })
})
