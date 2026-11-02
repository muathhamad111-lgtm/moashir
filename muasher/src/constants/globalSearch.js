import { ROUTE_NAMES } from './routeNames'
import { PERMISSIONS } from './permissionKeys'
import { ASSESSMENT_PATHS } from './assessment'

export const SEARCH_MIN_LENGTH = 2
export const SEARCH_MAX_LENGTH = 100
export const SEARCH_DEBOUNCE_MS = 300
export const RECENT_SEARCHES_LIMIT = 5

export const SEARCH_TYPES = {
  entities: 'entities',
  requests: 'requests',
  users: 'users',
}

export const SEARCH_PERMISSIONS = [
  PERMISSIONS.ENTITIES_VIEW,
  PERMISSIONS.REQUESTS_QUEUE_VIEW,
  PERMISSIONS.REQUESTS_ASSIGNED_VIEW,
  PERMISSIONS.USERS_VIEW,
]

export function canUseGlobalSearch(can) {
  return SEARCH_PERMISSIONS.some((permission) => can(permission))
}

export const SEARCH_TYPE_ICONS = {
  [SEARCH_TYPES.entities]: 'fa-solid fa-building-ngo',
  [SEARCH_TYPES.requests]: 'fa-solid fa-inbox',
  [SEARCH_TYPES.users]: 'fa-solid fa-user',
}

const DESTINATIONS = {
  [SEARCH_TYPES.entities]: (item) => ({
    to: { name: ROUTE_NAMES.ADMIN_ENTITY, params: { id: item.id } },
    prefill: '',
  }),
  [SEARCH_TYPES.requests]: (item) => ({
    to: {
      name: item.path === ASSESSMENT_PATHS.SELF ? ROUTE_NAMES.ADMIN_ASSESSMENT : ROUTE_NAMES.REQUESTS_DETAIL,
      params: { id: item.id },
    },
    prefill: '',
  }),
  [SEARCH_TYPES.users]: (item, label) => ({
    to: { name: ROUTE_NAMES.ADMIN_USERS },
    prefill: label,
  }),
}

export function searchDestination(item, label = '') {
  const build = DESTINATIONS[item?.type]
  return build ? build(item, label) : null
}

export const SEARCH_SHORTCUT_CODE = 'KeyK'
export const SEARCH_SHORTCUT_SLASH = '/'
export const SEARCH_KEY_SHORTCUTS = 'Control+K Meta+K /'

export const PERSONAL_DATA_PATTERNS = [/@/, /(?:\d[\s-]?){6,}/]
