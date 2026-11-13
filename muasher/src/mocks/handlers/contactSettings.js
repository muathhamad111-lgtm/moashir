import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope, errorResponse } from '../contract/responses'
import { currentSessionUser } from '../stores/sessionStore'
import { sessionCan, sessionIsSuperAdmin } from '../stores/sessionPermissions'
import { inboxListAll } from '../stores/contactInbox'

const LATENCY_MS = 240
const SETTINGS = 'settings.general.manage'
const PRIVACY = 'privacy.requests.manage'
const LIMITS = { min: 1, max: 120 }
const REASON_MIN = 10
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

function privacyRefusal(body) {
  const fields = {}
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(body.email ?? ''))
    fields.email = ['Enter a valid e-mail address.']
  if ((body.reason ?? '').trim().length < REASON_MIN)
    fields.reason = ['The reason must be at least 10 characters.']
  return Object.keys(fields).length
    ? errorResponse(422, 'validation_failed', 'The given data was invalid.', fields)
    : null
}

function messagesOf(email) {
  return inboxListAll().filter(
    (message) => message.sender.email.toLowerCase() === String(email).toLowerCase(),
  )
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

  http.post('/api/v1/privacy/contact-messages/export', async ({ request }) => {
    await delay(LATENCY_MS)
    const refused = refusal(PRIVACY)
    if (refused) return refused
    const body = await request.json()
    const invalid = privacyRefusal(body)
    if (invalid) return invalid
    return envelope({
      generatedAt: new Date().toISOString(),
      email: body.email,
      messages: messagesOf(body.email).map((message) => ({
        reference: message.reference,
        enquiryType: message.enquiryType,
        status: message.status,
        senderName: message.sender.name,
        senderEmail: message.sender.email,
        createdAt: message.createdAt,
        closedAt: message.closedAt,
        entries: message.entries
          .filter((entry) => entry.kind !== 'note')
          .map((entry) => ({ kind: entry.kind, body: entry.body, createdAt: entry.createdAt })),
      })),
    })
  }),

  http.post('/api/v1/privacy/contact-messages/erase', async ({ request }) => {
    await delay(LATENCY_MS)
    const refused = refusal(PRIVACY)
    if (refused) return refused
    const body = await request.json()
    const invalid = privacyRefusal(body)
    if (invalid) return invalid
    const matched = messagesOf(body.email)
    return envelope({
      erasedMessages: matched.length,
      erasedEntries: matched.reduce((sum, message) => sum + message.entries.length, 0),
    })
  }),
]
