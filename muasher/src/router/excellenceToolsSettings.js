import { ROUTE_NAMES } from '@/constants/routeNames'
import { EXCELLENCE_TOOLS_SETTINGS_ENABLED } from '@/constants/insights'

// A hidden excellence-tools settings page opened by its URL lands on the settings hub.
export function excellenceToolsSettingsGuard(enabled = EXCELLENCE_TOOLS_SETTINGS_ENABLED) {
  return () => (enabled ? true : { name: ROUTE_NAMES.ADMIN_SETTINGS })
}
