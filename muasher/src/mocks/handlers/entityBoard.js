import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope, errorResponse } from '../contract/responses'
import { currentSessionUser } from '../stores/sessionStore'
import { entityActionItems } from '../stores/entityBoard/entityActionItems'
import { entityBoardSummary } from '../stores/entityBoard/entityBoardSummary'

const LATENCY_MS = 300
const ENTITY_BOARD = 'entity'
const ENTITY_ROLE = 'entity'

function signedIn() {
  return currentSessionUser()
}

function unauthenticated() {
  return errorResponse(401, 'unauthenticated', 'Unauthenticated.')
}

function itemsOf(user) {
  return user.roles.includes(ENTITY_ROLE) ? entityActionItems(user) : []
}

export const entityBoardHandlers = [
  http.get('/api/v1/dashboard/action-center', async () => {
    await delay(LATENCY_MS)
    const user = signedIn()
    if (!user) return unauthenticated()
    const items = itemsOf(user)
    return envelope(items, { total: items.reduce((sum, item) => sum + item.count, 0) })
  }),

  http.get('/api/v1/dashboard/summary', async ({ request }) => {
    if (new URL(request.url).searchParams.get('board') !== ENTITY_BOARD) return undefined
    await delay(LATENCY_MS)
    const user = signedIn()
    if (!user) return unauthenticated()
    if (!user.roles.includes(ENTITY_ROLE)) return errorResponse(403, 'forbidden', 'Forbidden.')
    return envelope(entityBoardSummary(user))
  }),
]
