import { ROUTE_NAMES as R } from './routeNames'

export const PORTAL_AREAS = Object.freeze({
  JOURNEY: 'journey',
  ASSESSMENTS: 'assessments',
  CERTIFICATES: 'certificates',
  PAYMENTS: 'payments',
  MESSAGES: 'messages',
  MORE: 'more',
})

const A = PORTAL_AREAS

export const PORTAL_AREA_ROUTES = Object.freeze({
  [A.JOURNEY]: [R.APP_DASHBOARD],
  [A.ASSESSMENTS]: [
    R.ASSESSMENT_HISTORY,
    R.ASSESSMENT_SELF,
    R.ASSESSMENT_SELF_RESULT,
    R.ASSESSMENT_ACCREDITED_FORM,
    R.ASSESSMENT_ACCREDITED_RESULT,
    R.ASSESSMENT_ACCREDITED_FEEDBACK,
    R.ASSESSMENT_ADVANCED_FORM,
    R.ASSESSMENT_ADVANCED_RESULT,
  ],
  [A.CERTIFICATES]: [R.CERTIFICATES_INDEX, R.REPORTS_FEEDBACK],
  [A.PAYMENTS]: [R.PAYMENTS_MINE, R.PAYMENTS_DETAIL, R.PAYMENTS_RETURN, R.PAYMENTS_COMPLETE],
  [A.MESSAGES]: [R.MESSAGES_INDEX, R.MESSAGES_THREAD],
  [A.MORE]: [
    R.PORTAL_MORE,
    R.PORTAL_HELP,
    R.NOTIFICATIONS_INDEX,
    R.ENTITY_PROFILE,
    R.ACCOUNT_PROFILE,
    R.BAG_INDEX,
    R.BAG_COMPARISON,
    R.PORTAL_TOOL_MATURITY,
    R.PORTAL_TOOL_STRENGTHS,
    R.PORTAL_TOOL_BOARD_REPORTS,
    R.PORTAL_TOOL_EVIDENCE_LIBRARY,
    R.PORTAL_TOOL_PERIODIC_REVIEW,
    R.PORTAL_TOOL_ROADMAP,
    R.PORTAL_TOOL_NATIONAL_IMPACT,
    R.PORTAL_TOOL_TRENDS,
  ],
})

export const PORTAL_TOOLS = Object.freeze([
  {
    key: 'maturity',
    slug: 'maturity',
    icon: 'chart-bar',
    routeName: R.PORTAL_TOOL_MATURITY,
    live: true,
  },
  {
    key: 'strengths',
    slug: 'strengths-and-gaps',
    icon: 'target',
    routeName: R.PORTAL_TOOL_STRENGTHS,
    live: true,
  },
  {
    key: 'boardReports',
    slug: 'board-reports',
    icon: 'docs',
    routeName: R.PORTAL_TOOL_BOARD_REPORTS,
    live: true,
  },
  {
    key: 'evidenceLibrary',
    slug: 'evidence-library',
    icon: 'attachment',
    routeName: R.PORTAL_TOOL_EVIDENCE_LIBRARY,
  },
  {
    key: 'periodicReview',
    slug: 'periodic-review',
    icon: 'calendar',
    routeName: R.PORTAL_TOOL_PERIODIC_REVIEW,
  },
  { key: 'roadmap', slug: 'roadmap', icon: 'chart-up', routeName: R.PORTAL_TOOL_ROADMAP },
  {
    key: 'nationalImpact',
    slug: 'national-impact',
    icon: 'globe',
    routeName: R.PORTAL_TOOL_NATIONAL_IMPACT,
  },
  { key: 'trends', slug: 'trends', icon: 'lightbulb', routeName: R.PORTAL_TOOL_TRENDS },
])

export const PORTAL_TOOL_ROUTES = Object.freeze(PORTAL_TOOLS.map((tool) => tool.routeName))

export const PORTAL_TEASER_TOOLS = Object.freeze(PORTAL_TOOLS.filter((tool) => !tool.live))

export const PORTAL_SIDE_NAV_ITEMS = Object.freeze([
  { key: 'journey', routeName: R.APP_DASHBOARD, icon: 'grid-vertical', routes: [R.APP_DASHBOARD] },
  {
    key: 'assessments',
    routeName: R.ASSESSMENT_HISTORY,
    icon: 'clipboard-check',
    routes: PORTAL_AREA_ROUTES[A.ASSESSMENTS],
  },
  {
    key: 'certificates',
    routeName: R.CERTIFICATES_INDEX,
    icon: 'award',
    routes: PORTAL_AREA_ROUTES[A.CERTIFICATES],
  },
  {
    key: 'payments',
    routeName: R.PAYMENTS_MINE,
    icon: 'credit-card',
    routes: PORTAL_AREA_ROUTES[A.PAYMENTS],
  },
  {
    key: 'messages',
    routeName: R.MESSAGES_INDEX,
    icon: 'chat',
    routes: PORTAL_AREA_ROUTES[A.MESSAGES],
    badge: 'unreadReplies',
  },
  {
    key: 'notifications',
    routeName: R.NOTIFICATIONS_INDEX,
    icon: 'bell',
    routes: [R.NOTIFICATIONS_INDEX],
    badge: 'unreadNotifications',
  },
  {
    key: 'entityProfile',
    routeName: R.ENTITY_PROFILE,
    icon: 'building',
    routes: [R.ENTITY_PROFILE, R.ACCOUNT_PROFILE],
  },
  { key: 'help', routeName: R.PORTAL_HELP, icon: 'question-circle', routes: [R.PORTAL_HELP] },
])

export const PORTAL_TAB_ITEMS = Object.freeze([
  {
    area: A.JOURNEY,
    routeName: R.APP_DASHBOARD,
    labelKey: 'portal.nav.journey',
    icon: 'grid-vertical',
  },
  {
    area: A.ASSESSMENTS,
    routeName: R.ASSESSMENT_HISTORY,
    labelKey: 'portal.nav.assessments',
    icon: 'clipboard-check',
  },
  {
    area: A.CERTIFICATES,
    routeName: R.CERTIFICATES_INDEX,
    labelKey: 'portal.nav.certificates',
    icon: 'award',
  },
  { area: A.MORE, routeName: R.PORTAL_MORE, labelKey: 'portal.nav.more', icon: 'dots-horizontal' },
])

export const PORTAL_TASK_ROUTES = Object.freeze([
  R.ASSESSMENT_SELF,
  R.ASSESSMENT_ACCREDITED_FORM,
  R.ASSESSMENT_ADVANCED_FORM,
  R.PAYMENTS_RETURN,
  R.PAYMENTS_COMPLETE,
])

export const PORTAL_MOBILE_TASK_ROUTES = Object.freeze([R.MESSAGES_THREAD])

export const ATTENTION_STRIP_DESKTOP_LIMIT = 3

export const JOURNEY_GLANCE_NOTIFICATIONS = 3
