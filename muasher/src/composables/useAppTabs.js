import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { usePermissions } from '@/composables/usePermissions'
import { usePendingNavCounts } from '@/composables/usePendingNavCounts'
import {
  APP_NAV_ITEMS,
  APP_TAB_SLOTS,
  DASHBOARD_NAV_ID,
  ROLE_TAB_PRIORITY,
  SETTINGS_NAV_ITEM,
  SUPER_ADMIN_TAB_ROLE,
  filterNavByAccess,
  isNavItemActive,
} from '@/constants/navigation'
import { ROUTE_NAMES } from '@/constants/routeNames'

export const MORE_TAB_ID = 'more'

/** The nav item ids an account's phone tabs prefer, from its roles' priority lists, best first. */
export function tabPriorityOf(roles = [], isSuperAdmin = false) {
  const tabRoles = isSuperAdmin ? [SUPER_ADMIN_TAB_ROLE, ...roles] : roles
  return [...new Set(tabRoles.flatMap((role) => ROLE_TAB_PRIORITY[role] ?? []))]
}

/**
 * The phone tab items: the dashboard first (when allowed), then up to `slots` allowed items in the
 * order of `priority`. Items the account may not open never become tabs.
 */
export function selectTabItems(allowedItems, priority, slots = APP_TAB_SLOTS) {
  const byId = new Map(allowedItems.map((item) => [item.id, item]))
  const ranked = priority
    .filter((id) => id !== DASHBOARD_NAV_ID && byId.has(id))
    .slice(0, slots)
    .map((id) => byId.get(id))
  const dashboard = byId.get(DASHBOARD_NAV_ID)
  return dashboard ? [dashboard, ...ranked] : ranked
}

/**
 * The back office's phone navigation, built from the sidebar config and its permission filter
 * (plus Settings, which the desktop reaches from the navbar): the tab items, the rest for «المزيد»,
 * the active tab («المزيد» owns every route no tab owns), whether the current route is a tab's own
 * screen, and the route of the tab that owns the current screen.
 */
export function useAppTabs() {
  const route = useRoute()
  const router = useRouter()
  const auth = useAuthStore()
  const { canAccess } = usePermissions()
  const { withPendingCounts } = usePendingNavCounts()

  const allowedItems = computed(() =>
    filterNavByAccess([...APP_NAV_ITEMS, SETTINGS_NAV_ITEM], canAccess),
  )
  const priority = computed(() => tabPriorityOf(auth.roles, auth.isSuperAdmin))
  const tabItems = computed(() =>
    withPendingCounts(selectTabItems(allowedItems.value, priority.value)),
  )
  const tabIds = computed(() => new Set(tabItems.value.map((item) => item.id)))
  const moreItems = computed(() =>
    withPendingCounts(allowedItems.value.filter((item) => !tabIds.value.has(item.id))),
  )

  // A tab owns its own routes (as in the sidebar) and, on phones, the screens below its path (a
  // request opened from the requests tab keeps that tab lit).
  const tabPaths = computed(
    () =>
      new Map(
        tabItems.value.map((item) => [item.id, router.resolve({ name: item.routeName }).path]),
      ),
  )
  const ownsRoute = (item) =>
    isNavItemActive(item, route.name) || route.path.startsWith(`${tabPaths.value.get(item.id)}/`)
  const owningTab = computed(() => tabItems.value.find(ownsRoute))
  const activeTabId = computed(() => owningTab.value?.id ?? MORE_TAB_ID)
  const isTabRoot = computed(
    () =>
      route.name === ROUTE_NAMES.APP_MORE ||
      tabItems.value.some((item) => item.routeName === route.name),
  )
  const owningTabRoute = computed(() =>
    owningTab.value ? { name: owningTab.value.routeName } : { name: ROUTE_NAMES.APP_MORE },
  )

  return { tabItems, moreItems, activeTabId, isTabRoot, owningTabRoute }
}
