import { accessFor } from './routeAccess'
import { PERMISSIONS } from './permissionKeys'
import { ROUTE_NAMES } from './routeNames'
import { PATH_NAV_KINDS } from './paths'

export const APP_NAV_ITEMS = [
  {
    id: 'dashboard',
    group: 'overview',
    labelKey: 'nav.dashboard',
    icon: 'gauge',
    routeName: ROUTE_NAMES.APP_DASHBOARD,
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
  },
  {
    id: 'bag-manage',
    group: 'assessment',
    labelKey: 'nav.bagManage',
    icon: 'list-check',
    routeName: ROUTE_NAMES.BAG_MANAGE,
  },
  {
    id: 'notifications',
    group: 'overview',
    labelKey: 'nav.notifications',
    icon: 'bell',
    routeName: ROUTE_NAMES.NOTIFICATIONS_INDEX,
  },
  {
    id: 'admin-kpis',
    group: 'content',
    labelKey: 'nav.adminKpis',
    icon: 'chart-line',
    routeName: ROUTE_NAMES.ADMIN_KPIS,
  },
  {
    id: 'admin-criteria',
    group: 'content',
    labelKey: 'nav.adminCriteria',
    icon: 'hierarchy',
    routeName: ROUTE_NAMES.ADMIN_CRITERIA,
  },
  {
    id: 'admin-contact-messages',
    group: 'content',
    labelKey: 'nav.contactMessages',
    icon: 'envelope',
    routeName: ROUTE_NAMES.ADMIN_CONTACT_MESSAGES,
  },
  {
    id: 'finance-overview',
    group: 'finance',
    labelKey: 'nav.financeOverview',
    icon: 'pie-chart',
    routeName: ROUTE_NAMES.FINANCE_OVERVIEW,
  },
  {
    id: 'finance-transactions',
    group: 'finance',
    labelKey: 'nav.financeTransactions',
    icon: 'sar',
    routeName: ROUTE_NAMES.FINANCE_TRANSACTIONS,
  },
  {
    id: 'finance-transfers',
    group: 'finance',
    labelKey: 'nav.financeTransfers',
    icon: 'inbox',
    routeName: ROUTE_NAMES.FINANCE_TRANSFERS,
  },
  {
    id: 'finance-refunds',
    group: 'finance',
    labelKey: 'nav.financeRefunds',
    icon: 'undo',
    routeName: ROUTE_NAMES.FINANCE_REFUNDS,
  },
  {
    id: 'finance-payment-events',
    group: 'finance',
    labelKey: 'nav.financePaymentEvents',
    icon: 'menu',
    routeName: ROUTE_NAMES.FINANCE_PAYMENT_EVENTS,
  },
  {
    id: 'admin-entities',
    group: 'entities',
    labelKey: 'nav.adminEntities',
    icon: 'building',
    routeName: ROUTE_NAMES.ADMIN_ENTITIES,
  },
  {
    id: 'admin-deleted-entities',
    group: 'entities',
    labelKey: 'nav.deletedEntities',
    icon: 'undo',
    routeName: ROUTE_NAMES.ADMIN_ENTITIES_DELETED,
  },
  {
    id: 'admin-entity-email-changes',
    group: 'entities',
    labelKey: 'nav.entityEmailChanges',
    icon: 'envelope',
    routeName: ROUTE_NAMES.ADMIN_ENTITY_EMAIL_CHANGES,
  },
  {
    id: 'admin-privacy-requests',
    group: 'entities',
    labelKey: 'nav.privacyRequests',
    icon: 'user-shield',
    routeName: ROUTE_NAMES.ADMIN_PRIVACY_REQUESTS,
  },
  {
    id: 'admin-users',
    group: 'administration',
    labelKey: 'nav.adminUsers',
    icon: 'users-two',
    routeName: ROUTE_NAMES.ADMIN_USERS,
  },
  {
    id: 'admin-permissions',
    group: 'administration',
    labelKey: 'nav.adminPermissions',
    icon: 'shield-info',
    routeName: ROUTE_NAMES.ADMIN_PERMISSIONS,
  },
  {
    id: 'admin-audit-log',
    group: 'administration',
    labelKey: 'nav.auditLog',
    icon: 'shield-info',
    routeName: ROUTE_NAMES.ADMIN_AUDIT_LOG,
  },
  {
    id: 'admin-settings',
    group: 'administration',
    labelKey: 'nav.adminSettings',
    icon: 'gear',
    routeName: ROUTE_NAMES.ADMIN_SETTINGS,
  },
  {
    id: 'admin-branding',
    group: 'administration',
    labelKey: 'nav.branding',
    icon: 'palette',
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
