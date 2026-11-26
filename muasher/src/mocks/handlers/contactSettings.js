import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope, errorResponse } from '../contract/responses'
import { currentSessionUser } from '../stores/sessionStore'
import { sessionCan, sessionIsSuperAdmin } from '../stores/sessionPermissions'

const LATENCY_MS = 240
const SETTINGS = 'settings.general.manage'
const LIMITS = { min: 1, max: 120 }
const state = { retentionMonths: 24 }

function refusal(permission) {
  if (!currentSessionUser()) return errorResponse(401, 'unauthenticated', 'Unauthenticated.')
  if (!sessionCan(permission) && !sessionIsSuperAdmin())
    return errorResponse(403, 'forbidden', 'Forbidden.')
  return null
}

function settingsBody() {
  return { retentionMonths: state.retentionMonths, retentionLimits: { ...LIMITS } }
}

function invalidMonths(value) {
  return !Number.isInteger(value) || value < LIMITS.min || value > LIMITS.max
}

export const contactSettingsHandlers = [
  http.get('/api/v1/settings/contact-messages', async () => {
    await delay(LATENCY_MS)
    return refusal(SETTINGS) ?? envelope(settingsBody())
  }),

  http.put('/api/v1/settings/contact-messages', async ({ request }) => {
    await delay(LATENCY_MS)
    const refused = refusal(SETTINGS)
    if (refused) return refused
    const body = await request.json()
    if (sessionIsSuperAdmin() && !body.reason)
      return errorResponse(422, 'reason_required', 'A reason is required for this action.')
    if (invalidMonths(body.retentionMonths))
      return errorResponse(422, 'validation_failed', 'The given data was invalid.', {
        retentionMonths: ['The retention must be a whole number of months from 1 to 120.'],
      })
    state.retentionMonths = body.retentionMonths
    return envelope(settingsBody())
  }),
]
