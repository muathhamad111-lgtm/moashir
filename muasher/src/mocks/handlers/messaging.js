import { HttpResponse, delay } from 'msw'
import { http } from '../contract/http'
import { segmentsFixture, recipientsFixture } from '../fixtures/messaging'
import { failsRandomly } from '../contract/faults'

function envelope(data, meta = {}) {
  return HttpResponse.json({ data, meta, errors: null })
}

function errorResponse(message, status, fieldErrors = {}) {
  return HttpResponse.json({ message, errors: fieldErrors, code: status }, { status })
}

const maybeFail = failsRandomly(0.06)

export const messagingHandlers = [
  http.post('/api/v1/messaging/sms/send', async ({ request }) => {
    await delay(1000)
    if (maybeFail(0.12)) return errorResponse('The SMS campaign could not be sent', 500)
    const body = await request.json()
    return envelope({ queued: true, segment: body.segment ?? 'all', estimatedCount: 42 })
  }),

  http.get('/api/v1/messaging/segments', async () => {
    await delay(280)
    if (maybeFail(0.05)) return errorResponse('Failed to load segments', 503)
    return envelope(segmentsFixture.map((item) => ({ ...item })))
  }),

  http.get('/api/v1/messaging/recipients', async ({ request }) => {
    await delay(300)
    if (maybeFail(0.05)) return errorResponse('Failed to load recipients', 503)
    const url = new URL(request.url)
    const search = (url.searchParams.get('search') ?? '').trim().toLowerCase()
    let items = recipientsFixture.map((item) => ({ ...item }))
    if (search) {
      items = items.filter(
        (item) =>
          item.name.ar.toLowerCase().includes(search) ||
          item.name.en.toLowerCase().includes(search) ||
          item.email.toLowerCase().includes(search),
      )
    }
    return envelope(items)
  }),

  http.post('/api/v1/messaging/email/send', async ({ request }) => {
    await delay(1000)
    if (maybeFail(0.12)) return errorResponse('The email campaign could not be sent', 500)
    const body = await request.json()
    if (body.segment === 'individual' && !body.recipientEntityId) {
      return errorResponse('Select a recipient entity', 422, {
        recipientEntityId: ['Select a recipient entity.'],
      })
    }
    return envelope({ queued: true, segment: body.segment ?? 'all', estimatedCount: 118 })
  }),

  http.post('/api/v1/messaging/abandoned-carts/campaign', async ({ request }) => {
    await delay(900)
    if (maybeFail(0.12)) return errorResponse('The marketing campaign could not be sent', 500)
    const body = await request.json()
    return envelope({
      queued: true,
      channel: body.channel ?? 'email',
      count: (body.cartIds ?? []).length,
    })
  }),
]
