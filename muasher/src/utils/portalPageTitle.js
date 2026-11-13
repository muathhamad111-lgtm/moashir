import { PORTAL_SIDE_NAV_ITEMS, PORTAL_TOOLS } from '@/constants/portal'

function navItemKey(routeName) {
  for (const item of PORTAL_SIDE_NAV_ITEMS) {
    const child = item.children?.find((entry) => entry.routes.includes(routeName))
    if (child) return `portal.sideNav.paths.${child.key}`
    if (!item.children && item.routes.includes(routeName)) return `portal.sideNav.items.${item.key}`
  }
  return null
}

function toolKey(routeName) {
  const tool = PORTAL_TOOLS.find((entry) => entry.routeName === routeName)
  return tool ? `portal.tools.items.${tool.key}.title` : null
}

export function portalPageTitleKey(route) {
  return navItemKey(route?.name) ?? toolKey(route?.name) ?? route?.meta?.titleKey ?? null
}
