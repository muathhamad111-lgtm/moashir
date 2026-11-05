import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope, noContent } from '../contract/responses'
import { MockRejection, rejectionResponse } from '../contract/rejection'
import { currentSessionUser } from '../stores/sessionStore'
import { sessionCan, sessionIsSuperAdmin } from '../stores/sessionPermissions'
import {
  createDraft,
  discardDraft,
  legalVersion,
  publishDraft,
  updateDraft,
  versionsOf,
} from '../stores/legalVersionsStore'

const LATENCY_MS = 240
const CREATED = 201
const MANAGE = 'content.home.manage'
const KINDS = ['terms', 'privacy']

const MESSAGES = {
  unauthenticated: { ar: 'يلزم تسجيل الدخول.', en: 'Unauthenticated.' },
  forbidden: { ar: 'لا تملك صلاحية هذا الإجراء.', en: 'This action is not allowed.' },
  not_found: { ar: 'الإصدار غير موجود.', en: 'The version was not found.' },
  validation_failed: { ar: 'تحقق من البيانات المدخلة.', en: 'Check the entered data.' },
  reason_required: { ar: 'اذكر سبب النشر.', en: 'A reason is required to publish.' },
  'legal.draft_exists': {
    ar: 'توجد مسودة لهذا النص؛ عدّلها أو احذفها أولًا.',
    en: 'A draft of this text exists; edit or discard it first.',
  },
  'legal.version_taken': { ar: 'رقم الإصدار مستخدم.', en: 'The version label is taken.' },
  'legal.version_not_draft': {
    ar: 'هذا الإصدار منشور ولا يمكن تعديله. أنشئ مسودة جديدة لتغيير النص.',
    en: 'This version is published and cannot be changed. Create a new draft to change the text.',
  },
}

function guard() {
  if (!currentSessionUser()) throw new MockRejection(401, 'unauthenticated')
  if (!sessionCan(MANAGE) && !sessionIsSuperAdmin()) throw new MockRejection(403, 'forbidden')
}

function route(resolver) {
  return async (info) => {
    await delay(LATENCY_MS)
    try {
      guard()
      return await resolver(info)
    } catch (error) {
      if (error instanceof MockRejection) return rejectionResponse(error, info.request, MESSAGES)
      throw error
    }
  }
}

function knownKind(kind) {
  if (!KINDS.includes(kind)) throw new MockRejection(404, 'not_found')
  return kind
}

export const legalVersionsHandlers = [
  http.get(
    '/api/v1/admin/content/legal/:kind/versions',
    route(({ params }) => {
      const items = versionsOf(knownKind(params.kind))
      return envelope(items, {
        pagination: { page: 1, perPage: 50, total: items.length, lastPage: 1 },
      })
    }),
  ),
  http.post(
    '/api/v1/admin/content/legal/:kind/versions',
    route(async ({ params, request }) =>
      envelope(
        { ...createDraft(knownKind(params.kind), await request.json()) },
        {},
        { status: CREATED },
      ),
    ),
  ),
  http.get(
    '/api/v1/admin/content/legal/versions/:id',
    route(({ params }) => envelope({ ...legalVersion(params.id) })),
  ),
  http.put(
    '/api/v1/admin/content/legal/versions/:id',
    route(async ({ params, request }) =>
      envelope({ ...updateDraft(params.id, await request.json()) }),
    ),
  ),
  http.delete(
    '/api/v1/admin/content/legal/versions/:id',
    route(({ params }) => {
      discardDraft(params.id)
      return noContent()
    }),
  ),
  http.post(
    '/api/v1/admin/content/legal/versions/:id/publish',
    route(async ({ params, request }) => {
      const body = await request.json().catch(() => ({}))
      if (sessionIsSuperAdmin() && !body.reason) throw new MockRejection(422, 'reason_required')
      return envelope({ ...publishDraft(params.id) })
    }),
  ),
]
