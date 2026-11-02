export const ROLES = {
  SYSTEM_ADMIN: 'system_admin',
  CRITERIA_OFFICER: 'criteria_officer',
  REQUEST_FOLLOWER: 'request_follower',
  ASSESSOR: 'assessor',
  FILTERING_OFFICER: 'filtering_officer',
  AWARD_SECRETARY: 'award_secretary',
  CONTENT_OFFICER: 'content_officer',
  FINANCE_OFFICER: 'finance_officer',
  ENTITY: 'entity',
}

export const ROLE_KEYS = Object.freeze(Object.values(ROLES))

export function isKnownRole(role) {
  return ROLE_KEYS.includes(role)
}
