import { describe, expect, it } from 'vitest'
import {
  heldDashboardKeys,
  resolvePrimaryRole,
  resolveDashboardKey,
  DEFAULT_DASHBOARD_ROLE,
} from '@/constants/dashboardRoles'
import { ROLES } from '@/constants/roles'

describe('dashboard board selection (display only, never authorization)', () => {
  it('maps each canonical role to its board', () => {
    expect(resolveDashboardKey([ROLES.SYSTEM_ADMIN])).toBe('admin')
    expect(resolveDashboardKey([ROLES.ENTITY])).toBe('entity')
    expect(resolveDashboardKey([ROLES.REQUEST_FOLLOWER])).toBe('follower')
    expect(resolveDashboardKey([ROLES.ASSESSOR])).toBe('assessor')
    expect(resolveDashboardKey([ROLES.AWARD_SECRETARY])).toBe('secretary')
    expect(resolveDashboardKey([ROLES.FINANCE_OFFICER])).toBe('finance')
    expect(resolveDashboardKey([ROLES.CONTENT_OFFICER])).toBe('content-officer')
    expect(resolveDashboardKey([ROLES.FILTERING_OFFICER])).toBe('follower')
    expect(resolveDashboardKey([ROLES.CRITERIA_OFFICER])).toBe('content-officer')
  })

  it('picks the highest-precedence role for multi-role users', () => {
    expect(resolvePrimaryRole([ROLES.ENTITY, ROLES.SYSTEM_ADMIN])).toBe(ROLES.SYSTEM_ADMIN)
    expect(resolvePrimaryRole([ROLES.ASSESSOR, ROLES.REQUEST_FOLLOWER])).toBe(ROLES.REQUEST_FOLLOWER)
    expect(resolveDashboardKey([ROLES.ENTITY, ROLES.SYSTEM_ADMIN])).toBe('admin')
  })

  it('lands on the committee board first when the assessor holds a committee appointment', () => {
    const options = { hasCommitteeAppointment: true }
    expect(resolveDashboardKey([ROLES.ASSESSOR], options)).toBe('committee-assessor')
    expect(heldDashboardKeys([ROLES.ASSESSOR, ROLES.REQUEST_FOLLOWER], options)).toEqual(['committee-assessor', 'follower', 'assessor'])
    expect(heldDashboardKeys([ROLES.ASSESSOR])).toEqual(['assessor'])
  })

  it('falls back to the entity board for unknown or empty roles', () => {
    expect(resolvePrimaryRole([])).toBe(DEFAULT_DASHBOARD_ROLE)
    expect(resolveDashboardKey(['ghost-role'])).toBe('entity')
    expect(resolveDashboardKey(undefined)).toBe('entity')
    expect(heldDashboardKeys([ROLES.CONTENT_OFFICER, ROLES.REQUEST_FOLLOWER, ROLES.FILTERING_OFFICER])).toEqual([
      'follower',
      'content-officer',
    ])
    expect(heldDashboardKeys([])).toEqual([])
  })
})
