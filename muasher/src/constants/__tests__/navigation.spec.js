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
    expect(groups.map((group) => group.id)).toEqual(['overview', 'finance'])
  })

  it('treats child route names as active for their parent item', () => {
    const settings = APP_NAV_ITEMS.find((item) => item.id === 'admin-settings')
    expect(isNavItemActive(settings, 'admin.settings')).toBe(true)
    expect(isNavItemActive(settings, 'admin.settings.general')).toBe(true)
    expect(isNavItemActive(settings, 'admin.settingsx')).toBe(false)
    expect(isNavItemActive(settings, undefined)).toBe(false)
  })

  it('keeps branding outside the settings prefix so only one item is active', () => {
    const branding = APP_NAV_ITEMS.find((item) => item.id === 'admin-branding')
    const settings = APP_NAV_ITEMS.find((item) => item.id === 'admin-settings')
    expect(isNavItemActive(branding, 'admin.branding')).toBe(true)
    expect(isNavItemActive(settings, 'admin.branding')).toBe(false)
  })

  it('gates every sidebar item by a permission key, never by a role name', () => {
    expect(APP_NAV_ITEMS.filter((item) => item.roles !== undefined)).toEqual([])
    expect(
      APP_NAV_ITEMS.filter((item) => !navAccessOf(item)?.anyOf?.length).map((item) => item.id),
    ).toEqual([])
    expect(accessFor('admin.branding').anyOf).toEqual([PERMISSIONS.SETTINGS_BRANDING_MANAGE])
  })
})
