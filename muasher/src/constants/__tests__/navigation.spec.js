import { describe, expect, it } from 'vitest'
import {
  APP_NAV_ITEMS,
  NAV_GROUPS,
  filterNavByAccess,
  navAccessOf,
  groupNavItems,
  isNavItemActive,
} from '@/constants/navigation'
import { accessFor } from '@/constants/routeAccess'
import { PERMISSIONS } from '@/constants/permissionKeys'

describe('navigation constants', () => {
  it('assigns every sidebar item to a known module group', () => {
    const groupIds = NAV_GROUPS.map((group) => group.id)
    APP_NAV_ITEMS.forEach((item) => expect(groupIds).toContain(item.group))
  })

  it('groups items in the declared order and drops empty groups', () => {
    const granted = new Set([PERMISSIONS.DASHBOARD_VIEW, PERMISSIONS.FINANCE_OPERATIONS_VIEW])
    const canAccess = (access) => !access || access.anyOf.some((key) => granted.has(key))
    const groups = groupNavItems(filterNavByAccess(APP_NAV_ITEMS, canAccess))
    expect(groups.map((group) => group.id)).toEqual(['overview'])
    expect(groups[0].items.map((item) => item.id)).toEqual(['dashboard', 'finance'])
  })

  it('treats child route names as active for their parent item', () => {
    const dashboard = APP_NAV_ITEMS.find((item) => item.id === 'dashboard')
    expect(isNavItemActive(dashboard, 'app.dashboard')).toBe(true)
    expect(isNavItemActive(dashboard, 'app.dashboard.child')).toBe(true)
    expect(isNavItemActive(dashboard, 'app.dashboardx')).toBe(false)
    expect(isNavItemActive(dashboard, undefined)).toBe(false)
  })

  it('leaves settings, branding and the audit log to the settings hub', () => {
    const ids = APP_NAV_ITEMS.map((item) => item.id)
    expect(ids).not.toContain('admin-settings')
    expect(ids).not.toContain('admin-branding')
    expect(ids).not.toContain('admin-audit-log')
  })

  it('gates every sidebar item by a permission key, never by a role name', () => {
    expect(APP_NAV_ITEMS.filter((item) => item.roles !== undefined)).toEqual([])
    expect(
      APP_NAV_ITEMS.filter((item) => !navAccessOf(item)?.anyOf?.length).map((item) => item.id),
    ).toEqual([])
    expect(accessFor('admin.branding').anyOf).toEqual([
      PERMISSIONS.SETTINGS_BRANDING_MANAGE,
      PERMISSIONS.CONTENT_HOME_MANAGE,
    ])
  })
})

describe('Users section', () => {
  it('opens the permissions tab from the Users item when only the matrix is allowed', () => {
    const matrixOnly = (access) => access.anyOf.includes('roles.matrix.view')
    const users = filterNavByAccess(APP_NAV_ITEMS, matrixOnly).find(
      (item) => item.id === 'admin-users',
    )
    expect(users.routeName).toBe('admin.permissions')
    expect(isNavItemActive(users, 'admin.permissions')).toBe(true)
  })

  it('marks the Users item active on both of its tabs', () => {
    const users = APP_NAV_ITEMS.find((item) => item.id === 'admin-users')
    expect(isNavItemActive(users, 'admin.users')).toBe(true)
    expect(isNavItemActive(users, 'admin.permissions')).toBe(true)
    expect(APP_NAV_ITEMS.some((item) => item.id === 'admin-permissions')).toBe(false)
  })
})
