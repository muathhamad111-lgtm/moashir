import { HttpResponse, delay } from 'msw'
import { http } from '../contract/http'
import { dashboardFixture } from '../fixtures/dashboard'
import { failsRandomly } from '../contract/faults'

const ROLE_ALIASES = {
  admin: 'admin',
  secretary: 'secretary',
  'filtering-officer': 'follower',
  follower: 'follower',
  assessor: 'assessor',
  'committee-assessor': 'committee-assessor',
  'content-officer': 'content-officer',
  'criteria-officer': 'content-officer',
  finance: 'finance',
  entity: 'entity',
}

const DEFAULT_ROLE = 'entity'

function resolveRole(role) {
  if (role && ROLE_ALIASES[role]) return ROLE_ALIASES[role]
  return DEFAULT_ROLE
}

function envelope(data, meta = {}) {
  return HttpResponse.json({ data, meta, errors: null })
}

function errorResponse(message, status) {
  return HttpResponse.json({ message, errors: {}, code: status }, { status })
}

const maybeFail = failsRandomly(0.08)

export const dashboardHandlers = [
  http.get('/api/v1/dashboard/summary', async ({ request }) => {
    await delay(360)
    if (maybeFail()) {
      return errorResponse('Failed to load the dashboard summary', 503)
    }
    const url = new URL(request.url)
    const requestedRole = url.searchParams.get('role')
    const role = resolveRole(requestedRole)
    const summary = dashboardFixture[role]
    if (!summary) {
      return errorResponse('No dashboard is configured for this role', 404)
    }
    return envelope(
      { role, ...summary },
      { requestedRole, resolvedRole: role },
    )
  }),
]
