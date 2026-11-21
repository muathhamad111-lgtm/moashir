import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope, errorResponse } from '../contract/responses'
import { currentSessionUser } from '../stores/sessionStore'
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

export const entityBoardHandlers = [
  http.get('/api/v1/dashboard/summary', async ({ request }) => {
    if (new URL(request.url).searchParams.get('board') !== ENTITY_BOARD) return undefined
    await delay(LATENCY_MS)
    const user = signedIn()
    if (!user) return unauthenticated()
    if (!user.roles.includes(ENTITY_ROLE)) return errorResponse(403, 'forbidden', 'Forbidden.')
    return envelope(entityBoardSummary(user))
  }),
]
