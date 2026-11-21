import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { MockRejection, rejectionResponse } from '../contract/rejection'
import { paginate } from '../contract/pagination'
import { currentSessionUser } from '../stores/sessionStore'
import { sessionCan, sessionIsSuperAdmin } from '../stores/sessionPermissions'
import { actorDisplayName } from '../stores/actorName'
import {
  assignMessage,
  closeMessage,
  contactHandlers,
  inboxList,
  inboxMessage,
  noteMessage,
  reopenMessage,
  replyMessage,
} from '../stores/contactInbox'

const LATENCY_MS = 260
const CREATED = 201
const MANAGE = 'contact.messages.manage'

const MESSAGES = {
  unauthenticated: { ar: 'يلزم تسجيل الدخول.', en: 'Unauthenticated.' },
  forbidden: { ar: 'لا تملك صلاحية هذا الإجراء.', en: 'This action is not allowed.' },
  not_found: { ar: 'الرسالة غير موجودة.', en: 'The message was not found.' },
  validation_failed: { ar: 'تحقق من البيانات المدخلة.', en: 'Check the entered data.' },
  reason_required: { ar: 'اذكر سبب الإجراء.', en: 'A reason is required for this action.' },
  'contact.message_closed': {
    ar: 'أُغلقت هذه الرسالة؛ أعد فتحها أولًا.',
    en: 'This message is closed; reopen it first.',
  },
  'contact.assignee_not_eligible': {
    ar: 'لا يمكن إسناد الرسالة إلى هذا الحساب.',
    en: 'The message cannot be assigned to this account.',
  },
  'state.transition_illegal': {
    ar: 'لا يمكن تنفيذ هذا الإجراء على حالة الرسالة الحالية.',
    en: 'This action is not possible in the current state of the message.',
  },
}

function staff() {
  const user = currentSessionUser()
  if (!user) throw new MockRejection(401, 'unauthenticated')
  if (!sessionCan(MANAGE) && !sessionIsSuperAdmin()) throw new MockRejection(403, 'forbidden')
  return user
}

function author(user) {
  return { kind: 'staff', id: user.id, name: actorDisplayName(user, '') }
}

function requireReason(body) {
  if (sessionIsSuperAdmin() && !body?.reason) throw new MockRejection(422, 'reason_required')
}

function route(resolver) {
  return async (info) => {
    await delay(LATENCY_MS)
    try {
      return await resolver({ ...info, user: staff() })
    } catch (error) {
      if (error instanceof MockRejection) return rejectionResponse(error, info.request, MESSAGES)
      throw error
    }
  }
}

function write(perform, init = {}) {
  return route(async ({ params, request, user }) => {
    const body = await request.json().catch(() => ({}))
    requireReason(body)
    return envelope({ ...perform(params.id, body, user) }, {}, init)
  })
}

export const contactInboxHandlers = [
  http.get(
    '/api/v1/admin/contact-messages',
    route(({ request, user }) => {
      const query = new URL(request.url).searchParams
      const { rows, stats } = inboxList(query, user.id)
      const { items, pagination } = paginate(rows, query)
      return envelope(items, { stats, pagination })
    }),
  ),
  http.get(
    '/api/v1/admin/contact-messages/assignees',
    route(() => envelope(contactHandlers())),
  ),
  http.get(
    '/api/v1/admin/contact-messages/:id',
    route(({ params }) => envelope({ ...inboxMessage(params.id) })),
  ),
  http.post(
    '/api/v1/admin/contact-messages/:id/assign',
    write((id, body) => assignMessage(id, body.assigneeId ?? null)),
  ),
  http.post(
    '/api/v1/admin/contact-messages/:id/notes',
    write((id, body, user) => noteMessage(id, body.body, author(user)), { status: CREATED }),
  ),
  http.post(
    '/api/v1/admin/contact-messages/:id/replies',
    write((id, body, user) => replyMessage(id, body.body, author(user)), { status: CREATED }),
  ),
  http.post(
    '/api/v1/admin/contact-messages/:id/close',
    write((id, body, user) => closeMessage(id, { id: user.id, name: actorDisplayName(user, '') })),
  ),
  http.post(
    '/api/v1/admin/contact-messages/:id/reopen',
    write((id) => reopenMessage(id)),
  ),
]
