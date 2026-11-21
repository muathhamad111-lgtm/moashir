import { PERMISSIONS } from './permissionKeys'

export const IDENTITY_TABS = Object.freeze({
  general: 'general',
  visual: 'visual',
  menus: 'menus',
})

const TAB_PERMISSIONS = Object.freeze([
  [IDENTITY_TABS.general, PERMISSIONS.CONTENT_HOME_MANAGE],
  [IDENTITY_TABS.visual, PERMISSIONS.SETTINGS_BRANDING_MANAGE],
  [IDENTITY_TABS.menus, PERMISSIONS.CONTENT_HOME_MANAGE],
])

export function identityTabsFor(can) {
  return TAB_PERMISSIONS.filter(([, permission]) => can(permission)).map(([tab]) => tab)
}
