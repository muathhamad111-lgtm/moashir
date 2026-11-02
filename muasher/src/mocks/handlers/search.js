import { HttpResponse, delay } from 'msw'
import { http } from '../contract/http'
import { grantedPermissionKeys } from '../stores/permissionsStore'
import { SEARCH_MAX_LENGTH, SEARCH_MIN_LENGTH, SEARCH_TYPES } from '@/constants/globalSearch'
import { currentSessionUser } from '../stores/sessionStore'
import { accessOf } from '../stores/entityAccess'
import { ENTITY_ACCESS } from '../stores/entitySerializers'
import {
  normalizeSearchText,
  searchableLength,
  searchEntities,
  searchUsers,
} from '../stores/searchIndex'
import { searchAssessmentRequests } from '../stores/assessments/assessmentQueries'
import { requestSearchHit } from '../stores/assessments/selfSerializers'

const DEFAULT_LIMIT = 5
const MAX_LIMIT = 10

function envelope(data, meta = {}) {
  return HttpResponse.json({ data, meta, errors: null })
}

function errorResponse(message, status, code, fieldErrors = {}) {
  return HttpResponse.json({ message, code, errors: fieldErrors }, { status })
}

function group(type, rows, limit) {
  return { type, total: rows.length, items: rows.slice(0, limit) }
}

function providersFor(user) {
  const granted = new Set(grantedPermissionKeys(user.roles))
  const has = (key) => granted.has(key)
  const providers = []
  if (has('entities.view')) {
    const visible = accessOf(user) !== ENTITY_ACCESS.NONE
    providers.push([SEARCH_TYPES.entities, (needle) => (visible ? searchEntities(needle) : [])])
  }
  if (has('requests.queue.view') || has('requests.assigned.view')) {
    providers.push([
      SEARCH_TYPES.requests,
      (needle) => searchAssessmentRequests(user, needle, normalizeSearchText).map(requestSearchHit),
    ])
  }
  if (has('users.view')) providers.push([SEARCH_TYPES.users, (needle) => searchUsers(needle)])
  return providers
}

function readLimit(raw) {
  if (raw === null) return DEFAULT_LIMIT
  const value = Number(raw)
  return Number.isInteger(value) && value >= 1 && value <= MAX_LIMIT ? value : null
}

export const searchHandlers = [
  http.get('/api/v1/search', async ({ request }) => {
    await delay(220)
    const user = currentSessionUser()
    if (!user) return errorResponse('Unauthenticated.', 401, 'unauthenticated')
    const providers = providersFor(user)
    if (!providers.length) return errorResponse('This action is unauthorized.', 403, 'forbidden')
    const params = new URL(request.url).searchParams
    const query = params.get('q') ?? ''
    if (query.length > SEARCH_MAX_LENGTH || searchableLength(query) < SEARCH_MIN_LENGTH) {
      return errorResponse('The search term must contain at least 2 letters or digits.', 422, 'validation_failed', {
        q: ['The search term must contain at least 2 letters or digits.'],
      })
    }
    const limit = readLimit(params.get('limit'))
    if (limit === null) {
      return errorResponse('The limit must be between 1 and 10.', 422, 'validation_failed', {
        limit: ['The limit must be between 1 and 10.'],
      })
    }
    const needle = normalizeSearchText(query)
    return envelope(
      providers.map(([type, search]) => group(type, search(needle), limit)),
      { limit },
    )
  }),
]
