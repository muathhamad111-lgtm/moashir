import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { paginate } from '../contract/pagination'
import { MockRejection, messageEntry, rejectionResponse } from '../contract/rejection'
import { authMessages } from '../fixtures/authMessages'
import { identityMessages } from '../fixtures/identityMessages'
import { readJson } from '../stores/entityRejection'
import { currentSessionUser } from '../stores/sessionStore'
import { sessionCan } from '../stores/sessionPermissions'
import { sessionPayload } from '../stores/accounts'
import { findStaffUser } from '../stores/staffUsers'
import { nationalIdRequiredFor } from '../stores/authSettingsStore'
import {
  IDENTITY_CHANGE_STATUSES as S,
  approveChange,
  assignNationalId,
  cancelChange,
  changesOf,
  findChange,
  historyOf,
  isNationalIdTaken,
  listChanges,
  maskNationalId,
  nationalIdOf,
  openChange,
  pendingChangeOf,
  rejectChange,
  validNationalId,
} from '../stores/identityStore'

const REVIEW = 'users.identity_changes.review'
const MANAGE = 'users.manage'
const LATENCY = 180
const NOTE_MIN = 10
const REASON_MIN = 10
const MESSAGES = { ...authMessages, ...identityMessages }

const reject = {
  status: (status, code, fields) => new MockRejection(status, code, fields ? { fields } : {}),
  field: (field, code) =>
    new MockRejection(422, code, { fields: { [field]: [messageEntry(MESSAGES, code)] } }),
}

function route(resolve) {
  return async (info) => {
    await delay(LATENCY)
    try {
      return await resolve(info)
    } catch (error) {
      if (error instanceof MockRejection) return rejectionResponse(error, info.request, MESSAGES)
      throw error
    }
  }
}

function signedIn() {
  const user = currentSessionUser()
  if (!user) throw reject.status(401, 'unauthenticated')
  return { ...user, name: sessionPayload(user).name }
}

function reviewer() {
  const user = signedIn()
  if (!sessionCan(REVIEW)) throw reject.status(403, 'forbidden')
  return user
}

function boundChange(id) {
  const change = findChange(id)
  if (!change) throw reject.status(404, 'identity.not_found')
  return change
}

function assertNationalId(field, value, ownerId) {
  if (!validNationalId(value)) throw reject.field(field, 'identity.national_id_invalid')
  if (isNationalIdTaken(value, ownerId))
    throw reject.field(field, 'identity.national_id_unavailable')
}

function reviewResource(change, { full = false } = {}) {
  return {
    id: change.id,
    status: change.status,
    fields: [change.newName ? 'name' : null, change.newNationalId ? 'national_id' : null].filter(
      Boolean,
    ),
    user: { id: change.userId, name: change.userName },
    submittedBy: change.submitter,
    onBehalf: change.submitter?.id !== change.userId,
    currentName: change.userName,
    requestedName: change.newName,
    currentNationalIdMasked: maskNationalId(nationalIdOf(change.userId)),
    requestedNationalIdMasked: maskNationalId(change.newNationalId),
    ...(full
      ? { requestedNationalId: change.status === S.PENDING ? change.newNationalId : null }
      : {}),
    reason: change.reason,
    reviewedBy: change.reviewer,
    reviewNote: change.reviewNote,
    createdAt: change.createdAt,
    decidedAt: change.decidedAt,
  }
}

function ownResource(change) {
  const resource = reviewResource(change)
  return {
    id: resource.id,
    status: resource.status,
    fields: resource.fields,
    requestedName: resource.requestedName,
    requestedNationalIdMasked: resource.requestedNationalIdMasked,
    reason: resource.reason,
    reviewNote: resource.reviewNote,
    createdAt: resource.createdAt,
    decidedAt: resource.decidedAt,
  }
}

function assertPendingAndOthers(change, actor) {
  if (change.status !== S.PENDING) throw reject.status(409, 'state.transition_illegal')
  if (change.userId === actor.id || change.submitter?.id === actor.id)
    throw reject.status(403, 'identity_change.own_request')
}

export const identityHandlers = [
  http.get(
    '/api/v1/auth/register/options',
    route(() => envelope({ nationalIdRequired: nationalIdRequiredFor(['entity']) })),
  ),

  http.post(
    '/api/v1/me/national-id',
    route(async ({ request }) => {
      const user = signedIn()
      if (nationalIdOf(user.id)) throw reject.status(409, 'identity.national_id_locked')
      const value = String((await readJson(request)).nationalId ?? '')
      assertNationalId('nationalId', value, user.id)
      assignNationalId(user.id, value)
      return envelope({ nationalIdMasked: maskNationalId(value) }, {}, { status: 201 })
    }),
  ),

  http.get(
    '/api/v1/me/identity-changes',
    route(({ request }) => {
      const { items, pagination } = paginate(
        changesOf(signedIn().id),
        new URL(request.url).searchParams,
        10,
      )
      return envelope(items.map(ownResource), { pagination })
    }),
  ),

  http.post(
    '/api/v1/me/identity-changes',
    route(async ({ request }) => {
      const user = signedIn()
      const body = await readJson(request)
      if (String(body.reason ?? '').trim().length < REASON_MIN)
        throw reject.field('reason', 'validation_failed')
      if (body.newNationalId) assertNationalId('newNationalId', body.newNationalId, user.id)
      if (pendingChangeOf(user.id)) throw reject.status(409, 'identity_change.already_pending')
      const change = openChange({ user, submitter: user, ...body })
      return envelope(ownResource(change), {}, { status: 201 })
    }),
  ),

  http.post(
    '/api/v1/me/identity-changes/:id/cancel',
    route(({ params }) => {
      const user = signedIn()
      const change = boundChange(params.id)
      if (change.userId !== user.id) throw reject.status(404, 'identity.not_found')
      if (change.status !== S.PENDING) throw reject.status(409, 'state.transition_illegal')
      cancelChange(change)
      return envelope(ownResource(change))
    }),
  ),

  http.get(
    '/api/v1/identity-changes',
    route(({ request }) => {
      reviewer()
      const query = new URL(request.url).searchParams
      const { items, pagination } = paginate(
        listChanges(query.get('filter[status]') || S.PENDING),
        query,
        10,
      )
      return envelope(
        items.map((change) => reviewResource(change)),
        { pagination },
      )
    }),
  ),

  http.get(
    '/api/v1/identity-changes/:id',
    route(({ params }) => {
      reviewer()
      return envelope(reviewResource(boundChange(params.id), { full: true }))
    }),
  ),

  http.post(
    '/api/v1/identity-changes/:id/approve',
    route(async ({ params, request }) => {
      const actor = reviewer()
      const change = boundChange(params.id)
      assertPendingAndOthers(change, actor)
      approveChange(change, actor, (await readJson(request)).note)
      return envelope(reviewResource(change))
    }),
  ),

  http.post(
    '/api/v1/identity-changes/:id/reject',
    route(async ({ params, request }) => {
      const actor = reviewer()
      const change = boundChange(params.id)
      assertPendingAndOthers(change, actor)
      const note = String((await readJson(request)).note ?? '').trim()
      if (note.length < NOTE_MIN) throw reject.field('note', 'validation_failed')
      rejectChange(change, actor, note)
      return envelope(reviewResource(change))
    }),
  ),

  http.get(
    '/api/v1/users/:id/identity-history',
    route(({ params, request }) => {
      reviewer()
      const { items, pagination } = paginate(
        historyOf(Number(params.id)),
        new URL(request.url).searchParams,
        20,
      )
      return envelope(
        items.map((entry) => ({
          ...entry,
          oldValue: entry.field === 'national_id' ? maskNationalId(entry.oldValue) : entry.oldValue,
          newValue: entry.field === 'national_id' ? maskNationalId(entry.newValue) : entry.newValue,
        })),
        { pagination },
      )
    }),
  ),

  http.post(
    '/api/v1/users/:id/name-correction',
    route(async ({ params, request }) => {
      const actor = signedIn()
      if (!sessionCan(MANAGE) || Number(params.id) === actor.id)
        throw reject.status(403, 'forbidden')
      const target = findStaffUser(Number(params.id))
      if (!target) throw reject.status(404, 'identity.not_found')
      const body = await readJson(request)
      if (pendingChangeOf(target.id)) throw reject.status(409, 'identity_change.already_pending')
      const change = openChange({
        user: target,
        submitter: actor,
        newName: body.newName,
        reason: body.reason,
      })
      return envelope(reviewResource(change), {}, { status: 201 })
    }),
  ),
]
