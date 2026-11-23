import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope, noContent } from '../contract/responses'
import { MockRejection, rejectionResponse } from '../contract/rejection'
import { currentSessionUser } from '../stores/sessionStore'
import { sessionCan, sessionIsSuperAdmin } from '../stores/sessionPermissions'
import { actorDisplayName } from '../stores/actorName'
import {
  createDraft,
  discardDraft,
  legalVersion,
  publishDraft,
  updateDraft,
  versionsOf,
} from '../stores/legalVersionsStore'
import {
  acceptancesOf,
  hasAcceptedNda,
  ndaScopeRoles,
  ndaSettings,
  ndaText,
  nextManualReminderAt,
  recordNdaAcceptance,
  sendManualReminder,
  staffInScope,
  updateNdaSettings,
} from '../stores/ndaStore'
import { ndaRegister } from '../stores/ndaRegister'
import { currentNda, ndaRequiredFor } from '../stores/ndaCurrent'

const LATENCY_MS = 200
const CREATED = 201
const ACCEPTED = 202
const MANAGE = 'nda.manage'
const STAFF_ROLES = [
  'system_admin',
  'criteria_officer',
  'request_follower',
  'assessor',
  'filtering_officer',
  'award_secretary',
  'content_officer',
  'finance_officer',
]

const MESSAGES = {
  unauthenticated: { ar: 'يلزم تسجيل الدخول.', en: 'Unauthenticated.' },
  forbidden: { ar: 'لا تملك صلاحية هذا الإجراء.', en: 'This action is not allowed.' },
  not_found: { ar: 'غير موجود.', en: 'Not found.' },
  validation_failed: { ar: 'تحقق من البيانات المدخلة.', en: 'Check the entered data.' },
  'legal.draft_exists': {
    ar: 'توجد مسودة؛ عدّلها أو احذفها أولًا.',
    en: 'A draft exists; edit or discard it first.',
  },
  'legal.version_taken': { ar: 'رقم الإصدار مستخدم.', en: 'The version label is taken.' },
  'legal.version_not_draft': {
    ar: 'هذا الإصدار منشور ولا يمكن تعديله.',
    en: 'This version is published and cannot be changed.',
  },
  'nda.version_not_current': {
    ar: 'نسخة الاتفاقية هذه لم تعد سارية.',
    en: 'This agreement version is no longer current.',
  },
  'nda.no_draft': {
    ar: 'لا توجد مسودة لاتفاقية عدم الإفصاح.',
    en: 'There is no draft of the non-disclosure agreement.',
  },
  'nda.reminder_too_soon': {
    ar: 'أُرسل تذكير خلال آخر 24 ساعة.',
    en: 'A reminder was sent in the last 24 hours.',
  },
}

function signedIn() {
  const user = currentSessionUser()
  if (!user) throw new MockRejection(401, 'unauthenticated')
  return user
}

function manager() {
  signedIn()
  if (!sessionCan(MANAGE) && !sessionIsSuperAdmin()) throw new MockRejection(403, 'forbidden')
}

function route(resolver, guard = signedIn) {
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

function draftOrFail() {
  const draft = versionsOf('nda').find((item) => item.status === 'draft')
  if (!draft) throw new MockRejection(404, 'nda.no_draft')
  return draft
}

function withCounts(version) {
  const accepted = staffInScope().filter((user) => hasAcceptedNda(user.id, version.id)).length
  return { ...version, acceptancesCount: version.status === 'draft' ? null : accepted }
}

function overview() {
  const versions = versionsOf('nda')
  return {
    current: versions.find((item) => item.status === 'current') ?? null,
    draft: versions.find((item) => item.status === 'draft') ?? null,
    ...ndaSettings(),
    availableRoles: STAFF_ROLES,
    maxReminderDays: 30,
    nextManualReminderAt: nextManualReminderAt(),
  }
}

function impact() {
  const people = staffInScope()
  const roles = ndaScopeRoles().map((role) => ({
    role,
    count: people.filter((user) => user.roles.includes(role)).length,
  }))
  return { total: people.length, roles: [{ role: 'super_admin', count: 0 }, ...roles] }
}

function publish(body) {
  const draft = legalVersion(draftOrFail().id)
  if (
    String(body.reason ?? '').trim().length < 10 ||
    !body.confirmIrreversible ||
    !body.changeSummary?.ar ||
    !body.changeSummary?.en
  )
    throw new MockRejection(422, 'validation_failed', {
      fields: { reason: [MESSAGES.validation_failed] },
    })
  const published = publishDraft(draft.id)
  Object.assign(published, {
    changeSummary: { ...body.changeSummary },
    publishedBy: actorDisplayName(currentSessionUser(), ''),
  })
  return published
}

export const ndaHandlers = [
  http.get(
    '/api/v1/me/nda',
    route(() => {
      const current = currentNda()
      return envelope({
        acceptanceRequired: ndaRequiredFor(currentSessionUser()),
        version: current ? ndaText(current) : null,
      })
    }),
  ),
  http.post(
    '/api/v1/me/nda/acceptances',
    route(async ({ request }) => {
      const body = await request.json()
      recordNdaAcceptance(currentSessionUser(), currentNda(), body.termsVersionId)
      return envelope({ accepted: true, termsVersionId: Number(body.termsVersionId) })
    }),
  ),
  http.get(
    '/api/v1/me/nda/acceptances',
    route(() => {
      const items = acceptancesOf(currentSessionUser().id, versionsOf('nda'))
      return envelope(items, {
        pagination: { page: 1, perPage: 50, total: items.length, lastPage: 1 },
      })
    }),
  ),
  http.get(
    '/api/v1/me/nda/versions/:id',
    route(({ params }) => envelope(ndaText(legalVersion(params.id)))),
  ),
  http.get(
    '/api/v1/users/:id/nda-acceptances',
    route(({ params }) => {
      const items = acceptancesOf(Number(params.id), versionsOf('nda'))
      return envelope(items, {
        pagination: { page: 1, perPage: 50, total: items.length, lastPage: 1 },
      })
    }),
  ),
  http.get(
    '/api/v1/settings/nda',
    route(() => envelope(overview()), manager),
  ),
  http.put(
    '/api/v1/settings/nda',
    route(async ({ request }) => {
      updateNdaSettings(await request.json())
      return envelope(overview())
    }, manager),
  ),
  http.get(
    '/api/v1/settings/nda/versions',
    route(() => {
      const items = versionsOf('nda').map(withCounts)
      return envelope(items, {
        pagination: { page: 1, perPage: 50, total: items.length, lastPage: 1 },
      })
    }, manager),
  ),
  http.get(
    '/api/v1/settings/nda/versions/:id',
    route(({ params }) => envelope(withCounts({ ...legalVersion(params.id) })), manager),
  ),
  http.post(
    '/api/v1/settings/nda/draft',
    route(
      async ({ request }) =>
        envelope({ ...createDraft('nda', await request.json()) }, {}, { status: CREATED }),
      manager,
    ),
  ),
  http.put(
    '/api/v1/settings/nda/draft',
    route(
      async ({ request }) => envelope({ ...updateDraft(draftOrFail().id, await request.json()) }),
      manager,
    ),
  ),
  http.delete(
    '/api/v1/settings/nda/draft',
    route(() => {
      discardDraft(draftOrFail().id)
      return noContent()
    }, manager),
  ),
  http.get(
    '/api/v1/settings/nda/draft/impact',
    route(() => envelope(impact()), manager),
  ),
  http.post(
    '/api/v1/settings/nda/draft/publish',
    route(async ({ request }) => envelope({ ...publish(await request.json()) }), manager),
  ),
  http.get(
    '/api/v1/settings/nda/acceptances',
    route(({ request }) => {
      const page = ndaRegister(currentNda(), new URL(request.url).searchParams)
      return envelope(page.rows, page.meta)
    }, manager),
  ),
  http.post(
    '/api/v1/settings/nda/reminders',
    route(() => {
      const recipients = ndaRegister(currentNda(), new URLSearchParams('filter=pending')).meta
        .summary.pending
      sendManualReminder()
      return envelope(
        {
          recipients,
          sentAt: new Date().toISOString(),
          nextManualReminderAt: nextManualReminderAt(),
        },
        {},
        { status: ACCEPTED },
      )
    }, manager),
  ),
]
