import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { MockRejection, rejectionResponse } from '../contract/rejection'
import { currentSessionUser } from '../stores/sessionStore'
import { paginate } from '../contract/pagination'
import { submitContactMessage } from '../stores/contactMessages'
import {
  addSenderFollowUp,
  markSenderThreadSeen,
  openSenderThread,
  senderThreadCards,
  senderThreadDetail,
} from '../stores/senderThreads'
import { CONTACT_MOCK_FIELD_MESSAGES, CONTACT_MOCK_MESSAGES } from '../fixtures/contactMessages'

const SUBMIT_LATENCY_MS = 350
const CREATED = 201
const LIST_LATENCY_MS = 250
const THREAD_LATENCY_MS = 200
const THREAD_STATUSES = ['new', 'in_progress', 'replied', 'closed']

function fieldEntries(fields) {
  return Object.fromEntries(
    Object.entries(fields).map(([field, issue]) => [
      field,
      [{ ...CONTACT_MOCK_FIELD_MESSAGES[issue.key], params: issue.params }],
    ]),
  )
}

function rejected(rejection, request) {
  const localized = new MockRejection(rejection.status, rejection.code, {
    fields: fieldEntries(rejection.fields),
    headers: rejection.headers,
  })
  return rejectionResponse(localized, request, CONTACT_MOCK_MESSAGES)
}

function senderRoute(resolver) {
  return async (info) => {
    try {
      const user = currentSessionUser()
      if (!user) throw new MockRejection(401, 'unauthenticated')
      return await resolver({ ...info, user })
    } catch (error) {
      if (error instanceof MockRejection) return rejected(error, info.request)
      throw error
    }
  }
}

function wantedStatuses(query) {
  return (query.get('filter[status]') ?? '')
    .split(',')
    .filter(Boolean)
    .map((status) => status.replaceAll('-', '_'))
}

function summaryOf(threads) {
  const statusCounts = Object.fromEntries(
    THREAD_STATUSES.map((status) => [
      status,
      threads.filter((thread) => thread.status === status).length,
    ]),
  )
  return {
    statusCounts,
    unreadReplies: threads.filter((thread) => thread.hasUnreadReply).length,
  }
}

export const contactMessagesHandlers = [
  http.post('/api/v1/contact-messages', async ({ request }) => {
    await delay(SUBMIT_LATENCY_MS)
    const body = (await request.json().catch(() => null)) ?? {}
    const user = currentSessionUser()
    try {
      const receipt = submitContactMessage(body, { signedIn: Boolean(user) })
      const id = body.website ? null : openSenderThread(user, { ...body, ...receipt })
      return envelope(id ? { id, ...receipt } : receipt, {}, { status: CREATED })
    } catch (error) {
      if (error instanceof MockRejection) return rejected(error, request)
      throw error
    }
  }),

  http.get(
    '/api/v1/me/contact-messages',
    senderRoute(async ({ request, user }) => {
      await delay(LIST_LATENCY_MS)
      const query = new URL(request.url).searchParams
      const wanted = wantedStatuses(query)
      if (wanted.some((status) => !THREAD_STATUSES.includes(status)))
        throw new MockRejection(422, 'validation_failed', {
          fields: { 'filter.status': { key: 'status' } },
        })
      const threads = senderThreadCards(user)
      const { items, pagination } = paginate(
        threads.filter((thread) => !wanted.length || wanted.includes(thread.status)),
        query,
      )
      return envelope(items, { ...summaryOf(threads), pagination })
    }),
  ),

  http.get(
    '/api/v1/me/contact-messages/:id',
    senderRoute(async ({ params, user }) => {
      await delay(THREAD_LATENCY_MS)
      return envelope(senderThreadDetail(user, params.id))
    }),
  ),

  http.patch(
    '/api/v1/me/contact-messages/:id/seen',
    senderRoute(async ({ params, user }) => envelope(markSenderThreadSeen(user, params.id))),
  ),

  http.post(
    '/api/v1/me/contact-messages/:id/replies',
    senderRoute(async ({ params, request, user }) => {
      await delay(THREAD_LATENCY_MS)
      const body = (await request.json().catch(() => null)) ?? {}
      return envelope(addSenderFollowUp(user, params.id, body.body), {}, { status: CREATED })
    }),
  ),
]
