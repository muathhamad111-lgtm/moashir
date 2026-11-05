import { ROLES } from './roles'
import { ROUTE_NAMES } from './routeNames'

export const DEMO_PERSONAS = [
  {
    code: ROLES.SYSTEM_ADMIN,
    labelKey: 'users.roleLabels.system_admin',
    home: ROUTE_NAMES.ADMIN_SETTINGS,
  },
  { code: ROLES.ENTITY, labelKey: 'users.roleLabels.entity', home: ROUTE_NAMES.APP_DASHBOARD },
  {
    code: ROLES.ASSESSOR,
    labelKey: 'users.roleLabels.assessor',
    home: ROUTE_NAMES.REQUESTS_ASSIGNED,
  },
  {
    code: ROLES.REQUEST_FOLLOWER,
    labelKey: 'users.roleLabels.request_follower',
    home: ROUTE_NAMES.REQUESTS_INDEX,
  },
  {
    code: ROLES.FILTERING_OFFICER,
    labelKey: 'users.roleLabels.filtering_officer',
    home: ROUTE_NAMES.AWARD_FILTERING,
  },
  {
    code: 'committee_assessor',
    labelKey: 'dashboard.boards.committee-assessor',
    home: ROUTE_NAMES.AWARD_COMMITTEE,
  },
  {
    code: ROLES.AWARD_SECRETARY,
    labelKey: 'users.roleLabels.award_secretary',
    home: ROUTE_NAMES.AWARD_SECRETARY,
  },
  {
    code: ROLES.CRITERIA_OFFICER,
    labelKey: 'users.roleLabels.criteria_officer',
    home: ROUTE_NAMES.ADMIN_CRITERIA,
  },
  {
    code: ROLES.CONTENT_OFFICER,
    labelKey: 'users.roleLabels.content_officer',
    home: ROUTE_NAMES.ADMIN_KPIS,
  },
  {
    code: ROLES.FINANCE_OFFICER,
    labelKey: 'users.roleLabels.finance_officer',
    home: ROUTE_NAMES.APP_DASHBOARD,
  },
]
