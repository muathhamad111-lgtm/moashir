import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope, errorResponse } from '../contract/responses'
import { descriptorForPersona, sessionPayload } from '../stores/accounts'
import { startMockSession } from '../stores/sessionStore'

export function devSessionHandlers() {
  return [
  http.post('/api/v1/dev/demo-session', async ({ request }) => {
    await delay(150)
    const body = await request.json()
    if (!body.persona) return errorResponse(422, 'validation_failed', 'The persona is required.')
    const descriptor = descriptorForPersona(body.persona)
    startMockSession(descriptor)
    return envelope(sessionPayload(descriptor))
  }),
  ]
}
