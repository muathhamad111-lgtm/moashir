import { accessFor } from './routeAccess'
import { PERMISSIONS } from './permissionKeys'
import { ROUTE_NAMES } from './routeNames'
import { PATH_NAV_KINDS } from './paths'

// Below this navbar width the settings link, theme toggle and language switch move into the user menu.
export const NAVBAR_TOOLS_INLINE_MIN_WIDTH = 480

// A menu this short shows every group open under a plain heading; longer menus fold their groups.
export const SHORT_MENU_MAX_ITEMS = 10

export const APP_NAV_ITEMS = [
  {
    id: 'dashboard',
    group: 'overview',
    labelKey: 'nav.dashboard',
    icon: 'gauge',
    routeName: ROUTE_NAMES.APP_DASHBOARD,
  },
  {
    id: 'admin-entities',
    group: 'overview',
    labelKey: 'nav.adminEntities',
    icon: 'building',
    routeName: ROUTE_NAMES.ADMIN_ENTITIES,
    // Deleted entities and e-mail change requests open from buttons on the entities page.
    activeOn: [ROUTE_NAMES.ADMIN_ENTITIES_DELETED, ROUTE_NAMES.ADMIN_ENTITY_EMAIL_CHANGES],
  },
  {
    id: 'finance',
    group: 'overview',
    labelKey: 'nav.finance',
    icon: 'sar',
    routeName: ROUTE_NAMES.FINANCE_TRANSACTIONS,
    // Transfer review, operation details, exports and payment events open from the finance pages.
    activeOn: [
      ROUTE_NAMES.FINANCE_TRANSACTION,
      ROUTE_NAMES.FINANCE_TRANSFERS,
      ROUTE_NAMES.FINANCE_TRANSFER,
      ROUTE_NAMES.FINANCE_EXPORT,
      ROUTE_NAMES.FINANCE_PAYMENT_EVENTS,
    ],
  },
  {
    id: 'entity-profile',
    group: 'entity',
    labelKey: 'nav.entityProfile',
    icon: 'building',
    routeName: ROUTE_NAMES.ENTITY_PROFILE,
  },
  {
    id: 'assessment-history',
    group: 'entity',
    labelKey: 'nav.assessmentHistory',
    icon: 'refresh-dots',
    routeName: ROUTE_NAMES.ASSESSMENT_HISTORY,
  },
  {
    id: 'assessment-self',
    group: 'entity',
    labelKey: 'nav.assessmentSelf',
    icon: 'list-check',
    routeName: ROUTE_NAMES.ASSESSMENT_SELF,
  },
  {
    id: 'assessment-accredited',
    group: 'entity',
    labelKey: 'nav.assessmentAccredited',
    icon: 'clipboard-check',
    routeName: ROUTE_NAMES.ASSESSMENT_ACCREDITED,
    access: { anyOf: [PERMISSIONS.ASSESSMENT_ACCREDITED_MANAGE] },
  },
  {
    id: 'assessment-advanced',
    group: 'entity',
    labelKey: 'nav.assessmentAdvanced',
    icon: 'trophy',
    routeName: ROUTE_NAMES.ASSESSMENT_ADVANCED,
    access: { anyOf: [PERMISSIONS.ASSESSMENT_ADVANCED_MANAGE] },
  },
  {
    id: 'requests',
    group: 'assessment',
    labelKey: 'nav.requests',
    icon: 'inbox',
    routeName: ROUTE_NAMES.REQUESTS_INDEX,
    pathEntries: PATH_NAV_KINDS.QUEUE,
  },
  {
    id: 'requests-assigned',
    group: 'assessment',
    labelKey: 'nav.requestsAssigned',
    icon: 'user',
    routeName: ROUTE_NAMES.REQUESTS_ASSIGNED,
    pathEntries: PATH_NAV_KINDS.ASSIGNED,
  },
  {
    id: 'award-cycles',
    group: 'award',
    labelKey: 'nav.awardCycles',
    icon: 'calendar',
    routeName: ROUTE_NAMES.AWARD_CYCLES,
  },
  {
    id: 'award-committee-management',
    group: 'award',
    labelKey: 'nav.awardCommitteeManagement',
    icon: 'users-two',
    routeName: ROUTE_NAMES.AWARD_COMMITTEE_MANAGEMENT,
  },
  {
    id: 'award-filtering',
    group: 'award',
    labelKey: 'nav.awardFiltering',
    icon: 'filter',
    routeName: ROUTE_NAMES.AWARD_FILTERING,
  },
  {
    id: 'award-committee',
    group: 'award',
    labelKey: 'nav.awardCommittee',
    icon: 'scale',
    routeName: ROUTE_NAMES.AWARD_COMMITTEE,
  },
  {
    id: 'award-committee-discussions',
    group: 'award',
    labelKey: 'nav.awardCommitteeDiscussions',
    icon: 'chat',
    routeName: ROUTE_NAMES.AWARD_COMMITTEE_DISCUSSIONS,
  },
  {
    id: 'award-secretary',
    group: 'award',
    labelKey: 'nav.awardSecretary',
    icon: 'stamp',
    routeName: ROUTE_NAMES.AWARD_SECRETARY,
  },
  {
    id: 'award-results',
    group: 'award',
    labelKey: 'nav.awardResults',
    icon: 'trophy',
    routeName: ROUTE_NAMES.AWARD_RESULTS,
  },
  {
    id: 'certificates',
    group: 'entity',
    labelKey: 'nav.certificates',
    icon: 'award',
    routeName: ROUTE_NAMES.CERTIFICATES_INDEX,
  },
  {
    id: 'bag',
    group: 'entity',
    labelKey: 'nav.bag',
    icon: 'briefcase',
    routeName: ROUTE_NAMES.BAG_INDEX,
  },
  {
    id: 'payments',
    group: 'entity',
    labelKey: 'nav.payments',
    icon: 'receipt',
    routeName: ROUTE_NAMES.PAYMENTS_MINE,
  },
  {
    id: 'bag-requests',
    group: 'assessment',
    labelKey: 'nav.bagRequests',
    icon: 'folder',
    routeName: ROUTE_NAMES.BAG_REQUESTS,
  },
  {
    id: 'bag-evaluator',
    group: 'assessment',
    labelKey: 'nav.bagEvaluator',
    icon: 'briefcase',
    routeName: ROUTE_NAMES.BAG_EVALUATOR_REQUESTS,
    // A Kit plan opens from its row in the list.
    activeOn: [ROUTE_NAMES.BAG_MANAGE],
  },
  {
    id: 'admin-users',
    group: 'administration',
    labelKey: 'nav.adminUsers',
    icon: 'users-two',
    routeName: ROUTE_NAMES.ADMIN_USERS,
    activeOn: [ROUTE_NAMES.ADMIN_PERMISSIONS, ROUTE_NAMES.ADMIN_IDENTITY_CHANGES],
  },
  {
    id: 'admin-contact-messages',
    group: 'administration',
    labelKey: 'nav.contactMessages',
    icon: 'envelope',
    routeName: ROUTE_NAMES.ADMIN_CONTACT_MESSAGES,
  },
]

export const NAV_GROUPS = [
  { id: 'overview', headless: true },
  { id: 'entity', labelKey: 'shell.groups.entity' },
  { id: 'assessment', labelKey: 'shell.groups.assessment' },
  { id: 'award', labelKey: 'shell.groups.award' },
  { id: 'administration', headless: true },
]

export function navAccessOf(item) {
  return item.access ?? accessFor(item.routeName)
}

function isAllowed(access, canAccess) {
  return access !== null && canAccess(access)
}

function firstAllowedRoute(item, canAccess) {
  return [item.routeName, ...(item.activeOn ?? [])].find((name) =>
    isAllowed(accessFor(name), canAccess),
  )
}

export function filterNavByAccess(items, canAccess) {
  return items.flatMap((item) => {
    if (item.access) return isAllowed(item.access, canAccess) ? [item] : []
    const routeName = firstAllowedRoute(item, canAccess)
    return routeName ? [{ ...item, routeName }] : []
  })
}

export function groupNavItems(items, groups = NAV_GROUPS) {
  return groups
    .map((group) => ({ ...group, items: items.filter((item) => item.group === group.id) }))
    .filter((group) => group.items.length > 0)
}

export function isNavItemActive(item, routeName) {
  if (typeof routeName !== 'string') return false
  return (
    routeName === item.routeName ||
    routeName.startsWith(`${item.routeName}.`) ||
    Boolean(item.activeOn?.includes(routeName))
  )
}
