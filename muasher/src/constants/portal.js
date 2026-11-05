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
  ],
})

export const PORTAL_NAV_ITEMS = Object.freeze([
  {
    area: A.JOURNEY,
    routeName: R.APP_DASHBOARD,
    labelKey: 'portal.nav.journey',
    icon: 'route',
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
  {
    area: A.PAYMENTS,
    routeName: R.PAYMENTS_MINE,
    labelKey: 'portal.nav.payments',
    icon: 'credit-card',
  },
  {
    area: A.MESSAGES,
    routeName: R.MESSAGES_INDEX,
    labelKey: 'portal.nav.messages',
    icon: 'envelope',
    badge: 'unreadReplies',
  },
])

export const PORTAL_TAB_ITEMS = Object.freeze([
  ...PORTAL_NAV_ITEMS.filter((item) =>
    [A.JOURNEY, A.ASSESSMENTS, A.CERTIFICATES].includes(item.area),
  ),
  {
    area: A.MORE,
    routeName: R.PORTAL_MORE,
    labelKey: 'portal.nav.more',
    icon: 'dots-horizontal',
  },
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
