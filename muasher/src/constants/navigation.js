import { accessFor } from './routeAccess'
import { PERMISSIONS } from './permissionKeys'
import { ROUTE_NAMES } from './routeNames'

export const APP_NAV_ITEMS = [
  {
    id: 'dashboard',
    group: 'overview',
    labelKey: 'nav.dashboard',
    icon: 'fa-solid fa-gauge-high',
    routeName: ROUTE_NAMES.APP_DASHBOARD,
  },
  {
    id: 'entity-profile',
    group: 'entity',
    labelKey: 'nav.entityProfile',
    icon: 'fa-solid fa-building-ngo',
    routeName: ROUTE_NAMES.ENTITY_PROFILE,
  },
  {
    id: 'assessment-history',
    group: 'entity',
    labelKey: 'nav.assessmentHistory',
    icon: 'fa-solid fa-clock-rotate-left',
    routeName: ROUTE_NAMES.ASSESSMENT_HISTORY,
  },
  {
    id: 'assessment-self',
    group: 'entity',
    labelKey: 'nav.assessmentSelf',
    icon: 'fa-solid fa-list-check',
    routeName: ROUTE_NAMES.ASSESSMENT_SELF,
  },
  {
    id: 'assessment-accredited',
    group: 'entity',
    labelKey: 'nav.assessmentAccredited',
    icon: 'fa-solid fa-clipboard-check',
    routeName: ROUTE_NAMES.ASSESSMENT_ACCREDITED,
    access: { anyOf: [PERMISSIONS.ASSESSMENT_ACCREDITED_MANAGE] },
  },
  {
    id: 'assessment-advanced',
    group: 'entity',
    labelKey: 'nav.assessmentAdvanced',
    icon: 'fa-solid fa-trophy',
    routeName: ROUTE_NAMES.ASSESSMENT_ADVANCED,
    access: { anyOf: [PERMISSIONS.ASSESSMENT_ADVANCED_MANAGE] },
  },
  {
    id: 'requests',
    group: 'assessment',
    labelKey: 'nav.requests',
    icon: 'fa-solid fa-inbox',
    routeName: ROUTE_NAMES.REQUESTS_INDEX,
  },
  {
    id: 'requests-assigned',
    group: 'assessment',
    labelKey: 'nav.requestsAssigned',
    icon: 'fa-solid fa-clipboard-user',
    routeName: ROUTE_NAMES.REQUESTS_ASSIGNED,
  },
  {
    id: 'award-cycles',
    group: 'award',
    labelKey: 'nav.awardCycles',
    icon: 'fa-solid fa-calendar-days',
    routeName: ROUTE_NAMES.AWARD_CYCLES,
  },
  {
    id: 'award-committee-management',
    group: 'award',
    labelKey: 'nav.awardCommitteeManagement',
    icon: 'fa-solid fa-users-gear',
    routeName: ROUTE_NAMES.AWARD_COMMITTEE_MANAGEMENT,
  },
  {
    id: 'award-filtering',
    group: 'award',
    labelKey: 'nav.awardFiltering',
    icon: 'fa-solid fa-filter',
    routeName: ROUTE_NAMES.AWARD_FILTERING,
  },
  {
    id: 'award-committee',
    group: 'award',
    labelKey: 'nav.awardCommittee',
    icon: 'fa-solid fa-scale-balanced',
    routeName: ROUTE_NAMES.AWARD_COMMITTEE,
  },
  {
    id: 'award-secretary',
    group: 'award',
    labelKey: 'nav.awardSecretary',
    icon: 'fa-solid fa-stamp',
    routeName: ROUTE_NAMES.AWARD_SECRETARY,
  },
  {
    id: 'award-results',
    group: 'award',
    labelKey: 'nav.awardResults',
    icon: 'fa-solid fa-trophy',
    routeName: ROUTE_NAMES.AWARD_RESULTS,
  },
  {
    id: 'certificates',
    group: 'entity',
    labelKey: 'nav.certificates',
    icon: 'fa-solid fa-award',
    routeName: ROUTE_NAMES.CERTIFICATES_INDEX,
  },
  {
    id: 'bag',
    group: 'entity',
    labelKey: 'nav.bag',
    icon: 'fa-solid fa-briefcase',
    routeName: ROUTE_NAMES.BAG_INDEX,
  },
  {
    id: 'payments',
    group: 'entity',
    labelKey: 'nav.payments',
    icon: 'fa-solid fa-receipt',
    routeName: ROUTE_NAMES.PAYMENTS_MINE,
  },
  {
    id: 'bag-requests',
    group: 'assessment',
    labelKey: 'nav.bagRequests',
    icon: 'fa-solid fa-folder-tree',
    routeName: ROUTE_NAMES.BAG_REQUESTS,
  },
  {
    id: 'bag-evaluator',
    group: 'assessment',
    labelKey: 'nav.bagEvaluator',
    icon: 'fa-solid fa-briefcase-arrow-right',
    routeName: ROUTE_NAMES.BAG_EVALUATOR_REQUESTS,
  },
  {
    id: 'bag-manage',
    group: 'assessment',
    labelKey: 'nav.bagManage',
    icon: 'fa-solid fa-list-check',
    routeName: ROUTE_NAMES.BAG_MANAGE,
  },
  {
    id: 'notifications',
    group: 'overview',
    labelKey: 'nav.notifications',
    icon: 'fa-solid fa-bell',
    routeName: ROUTE_NAMES.NOTIFICATIONS_INDEX,
  },
  {
    id: 'admin-kpis',
    group: 'content',
    labelKey: 'nav.adminKpis',
    icon: 'fa-solid fa-chart-line',
    routeName: ROUTE_NAMES.ADMIN_KPIS,
  },
  {
    id: 'admin-criteria',
    group: 'content',
    labelKey: 'nav.adminCriteria',
    icon: 'fa-solid fa-sitemap',
    routeName: ROUTE_NAMES.ADMIN_CRITERIA,
  },
  {
    id: 'finance-overview',
    group: 'finance',
    labelKey: 'nav.financeOverview',
    icon: 'fa-solid fa-chart-pie',
    routeName: ROUTE_NAMES.FINANCE_OVERVIEW,
  },
  {
    id: 'finance-transactions',
    group: 'finance',
    labelKey: 'nav.financeTransactions',
    icon: 'fa-solid fa-money-bill-transfer',
    routeName: ROUTE_NAMES.FINANCE_TRANSACTIONS,
  },
  {
    id: 'finance-refunds',
    group: 'finance',
    labelKey: 'nav.financeRefunds',
    icon: 'fa-solid fa-rotate-left',
    routeName: ROUTE_NAMES.FINANCE_REFUNDS,
  },
  {
    id: 'finance-payment-events',
    group: 'finance',
    labelKey: 'nav.financePaymentEvents',
    icon: 'fa-solid fa-list-timeline',
    routeName: ROUTE_NAMES.FINANCE_PAYMENT_EVENTS,
  },
  {
    id: 'admin-entities',
    group: 'entities',
    labelKey: 'nav.adminEntities',
    icon: 'fa-solid fa-building-ngo',
    routeName: ROUTE_NAMES.ADMIN_ENTITIES,
  },
  {
    id: 'admin-deleted-entities',
    group: 'entities',
    labelKey: 'nav.deletedEntities',
    icon: 'fa-solid fa-trash-can-arrow-up',
    routeName: ROUTE_NAMES.ADMIN_ENTITIES_DELETED,
  },
  {
    id: 'admin-entity-email-changes',
    group: 'entities',
    labelKey: 'nav.entityEmailChanges',
    icon: 'fa-solid fa-envelope-circle-check',
    routeName: ROUTE_NAMES.ADMIN_ENTITY_EMAIL_CHANGES,
  },
  {
    id: 'admin-privacy-requests',
    group: 'entities',
    labelKey: 'nav.privacyRequests',
    icon: 'fa-solid fa-user-shield',
    routeName: ROUTE_NAMES.ADMIN_PRIVACY_REQUESTS,
  },
  {
    id: 'admin-users',
    group: 'administration',
    labelKey: 'nav.adminUsers',
    icon: 'fa-solid fa-users-gear',
    routeName: ROUTE_NAMES.ADMIN_USERS,
  },
  {
    id: 'admin-permissions',
    group: 'administration',
    labelKey: 'nav.adminPermissions',
    icon: 'fa-solid fa-shield-halved',
    routeName: ROUTE_NAMES.ADMIN_PERMISSIONS,
  },
  {
    id: 'admin-settings',
    group: 'administration',
    labelKey: 'nav.adminSettings',
    icon: 'fa-solid fa-gear',
    routeName: ROUTE_NAMES.ADMIN_SETTINGS,
  },
  {
    id: 'admin-branding',
    group: 'administration',
    labelKey: 'nav.branding',
    icon: 'fa-solid fa-palette',
    routeName: ROUTE_NAMES.ADMIN_BRANDING,
  },
]

export const NAV_GROUPS = [
  { id: 'overview', labelKey: 'shell.groups.overview' },
  { id: 'entity', labelKey: 'shell.groups.entity' },
  { id: 'entities', labelKey: 'shell.groups.entities' },
  { id: 'assessment', labelKey: 'shell.groups.assessment' },
  { id: 'award', labelKey: 'shell.groups.award' },
  { id: 'content', labelKey: 'shell.groups.content' },
  { id: 'finance', labelKey: 'shell.groups.finance' },
  { id: 'administration', labelKey: 'shell.groups.administration' },
]

export const PUBLIC_NAV_ITEMS = [
  { id: 'home', labelKey: 'nav.home', routeName: ROUTE_NAMES.PUBLIC_HOME },
  { id: 'kpis', labelKey: 'nav.kpis', routeName: ROUTE_NAMES.PUBLIC_KPIS },
  { id: 'lenses', labelKey: 'nav.lenses', routeName: ROUTE_NAMES.PUBLIC_LENSES },
]

export function navAccessOf(item) {
  return item.access ?? accessFor(item.routeName)
}

export function filterNavByAccess(items, canAccess) {
  return items.filter((item) => {
    const access = navAccessOf(item)
    return access !== null && canAccess(access)
  })
}

export function groupNavItems(items, groups = NAV_GROUPS) {
  return groups
    .map((group) => ({ ...group, items: items.filter((item) => item.group === group.id) }))
    .filter((group) => group.items.length > 0)
}

export function isNavItemActive(item, routeName) {
  if (typeof routeName !== 'string') return false
  return routeName === item.routeName || routeName.startsWith(`${item.routeName}.`)
}
