import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { usePermissions } from '@/composables/usePermissions'
import { useAuthStore } from '@/stores/auth'
import { ROLES } from '@/constants/roles'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { sessionUserFor } from '@/test-utils/session'

beforeEach(() => setActivePinia(createPinia()))

function signIn(roles, extra = {}) {
  useAuthStore().setUser(sessionUserFor(roles, extra))
  return usePermissions()
}

describe('usePermissions — server permissions, deny by default', () => {
  it('grants only the keys the session carries', () => {
    const { can } = signIn([], { permissions: [PERMISSIONS.USERS_VIEW] })
    expect(can(PERMISSIONS.USERS_VIEW)).toBe(true)
    expect(can(PERMISSIONS.USERS_MANAGE)).toBe(false)
  })

  it('denies unknown keys even if the session lists them', () => {
    const { can } = signIn([], { permissions: ['committeeManagement.manage', 'reports.export'] })
    expect(can('committeeManagement.manage')).toBe(false)
    expect(can('reports.export')).toBe(false)
    expect(can(undefined)).toBe(false)
  })

  it('gives the System Administrator no bypass', () => {
    const { can } = signIn([ROLES.SYSTEM_ADMIN])
    expect(can(PERMISSIONS.USERS_MANAGE)).toBe(true)
    expect(can(PERMISSIONS.AWARD_COMMITTEE_SCORE)).toBe(false)
    expect(can(PERMISSIONS.ASSESSMENT_SELF_MANAGE)).toBe(false)
  })

  it('does not treat the Super Admin flag as a client-side bypass', () => {
    const { can } = signIn([], { isSuperAdmin: true, permissions: [PERMISSIONS.AUDIT_VIEW] })
    expect(can(PERMISSIONS.AUDIT_VIEW)).toBe(true)
    expect(can(PERMISSIONS.USERS_MANAGE)).toBe(false)
  })

  it('denies everything when signed out', () => {
    const { can, canAny } = usePermissions()
    expect(can(PERMISSIONS.DASHBOARD_VIEW)).toBe(false)
    expect(canAny([PERMISSIONS.DASHBOARD_VIEW])).toBe(false)
  })

  it('checks any-of and all-of sets', () => {
    const { canAny, canAll } = signIn([], { permissions: [PERMISSIONS.CRITERIA_VIEW] })
    expect(canAny([PERMISSIONS.CRITERIA_MANAGE, PERMISSIONS.CRITERIA_VIEW])).toBe(true)
    expect(canAll([PERMISSIONS.CRITERIA_MANAGE, PERMISSIONS.CRITERIA_VIEW])).toBe(false)
    expect(canAll([])).toBe(false)
  })

  it('requires a committee appointment on top of the permission for committee routes', () => {
    const access = { anyOf: [PERMISSIONS.AWARD_COMMITTEE_ASSIGNED_VIEW], committeeAppointment: true }
    expect(signIn([ROLES.ASSESSOR]).canAccess(access)).toBe(false)
    expect(signIn([ROLES.ASSESSOR], { committeeAppointments: [{ cycleId: 'c-1' }] }).canAccess(access)).toBe(true)
    expect(signIn([ROLES.ASSESSOR]).canAccess(null)).toBe(true)
  })
})
