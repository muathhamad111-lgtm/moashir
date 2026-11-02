import { ROLES } from './roles'

export const COMMITTEE_BOARD_KEY = 'committee-assessor'

export const DASHBOARD_ROLE_PRECEDENCE = [
  ROLES.SYSTEM_ADMIN,
  ROLES.AWARD_SECRETARY,
  ROLES.FILTERING_OFFICER,
  ROLES.REQUEST_FOLLOWER,
  ROLES.ASSESSOR,
  ROLES.CONTENT_OFFICER,
  ROLES.CRITERIA_OFFICER,
  ROLES.FINANCE_OFFICER,
  ROLES.ENTITY,
]

export const DASHBOARD_ROLE_ALIASES = {
  [ROLES.SYSTEM_ADMIN]: 'admin',
  [ROLES.AWARD_SECRETARY]: 'secretary',
  [ROLES.FILTERING_OFFICER]: 'follower',
  [ROLES.REQUEST_FOLLOWER]: 'follower',
  [ROLES.ASSESSOR]: 'assessor',
  [ROLES.CONTENT_OFFICER]: 'content-officer',
  [ROLES.CRITERIA_OFFICER]: 'content-officer',
  [ROLES.FINANCE_OFFICER]: 'finance',
  [ROLES.ENTITY]: 'entity',
}

export const DEFAULT_DASHBOARD_ROLE = ROLES.ENTITY

export function resolvePrimaryRole(userRoles = []) {
  const present = DASHBOARD_ROLE_PRECEDENCE.find((role) => userRoles.includes(role))
  return present ?? DEFAULT_DASHBOARD_ROLE
}

export function heldDashboardKeys(userRoles = [], { hasCommitteeAppointment = false } = {}) {
  const roleKeys = DASHBOARD_ROLE_PRECEDENCE.filter((role) => userRoles.includes(role)).map(
    (role) => DASHBOARD_ROLE_ALIASES[role],
  )
  const keys = hasCommitteeAppointment ? [COMMITTEE_BOARD_KEY, ...roleKeys] : roleKeys
  return [...new Set(keys)]
}

export function resolveDashboardKey(userRoles = [], options = {}) {
  return heldDashboardKeys(userRoles, options)[0] ?? DASHBOARD_ROLE_ALIASES[DEFAULT_DASHBOARD_ROLE]
}
