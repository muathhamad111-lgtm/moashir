import {
  PROFILE_CHANGE_COUPLED,
  PROFILE_CHANGE_NEEDS,
  PROFILE_CHANGE_FILES as F,
  PROFILE_CHANGE_STATUSES as S,
  PROFILE_CHANGE_VERDICTS as V,
} from '@/constants/entityEnums'
import { MockRejection } from '../contract/rejection'
import { PROFILE_FIELD_LABELS } from '../fixtures/profileChanges'
import { entityReject } from './entityRejection'
import { applySection, snapshotOf } from './entitySectionSaves'
import { setMarketingConsent } from './entitiesStore'
import { recordProfileChanges } from './profileChangesStore'
import { actorDisplayName } from './actorName'
import { PROFILE_CHANGE_FIELDS, fieldsOfSection, sameValue } from './profileChangeFields'
import { ENTITY_KEY_OF_FILE, applyApprovedItems } from './profileChangeApply'
import { persistRequests, restoredRequests } from './profileChangePersistence'

const REASON_MIN = 10

let requests = restoredRequests()

export function resetProfileChangeRequests() {
  requests = []
  persistRequests(requests)
}

function nowIso() {
  return new Date().toISOString()
}

export function pendingRequestOf(entityId) {
  return (
    requests.find((request) => request.entityId === entityId && request.status === S.PENDING) ??
    null
  )
}

export function findRequest(id) {
  return requests.find((request) => request.id === id) ?? null
}

export function requestsOf(entityId) {
  return requests.filter((request) => request.entityId === entityId).sort(newestFirst)
}

function newestFirst(left, right) {
  return right.submittedAt.localeCompare(left.submittedAt)
}

export function listRequests({ status, entityId = null, matches = () => true }) {
  return requests
    .filter((request) => status === 'all' || request.status === status)
    .filter((request) => entityId === null || request.entityId === entityId)
    .filter(matches)
    .sort(newestFirst)
}

export function itemsOf(request) {
  return [...Object.keys(request.proposed), ...Object.keys(request.files)]
}

function openFor(entity, user) {
  const pending = pendingRequestOf(entity.id)
  if (pending) return pending
  return {
    id: crypto.randomUUID(),
    entityId: entity.id,
    status: S.PENDING,
    revision: 0,
    proposed: {},
    base: {},
    files: {},
    decisions: null,
    reviewNote: null,
    submittedBy: actorDisplayName(user),
    reviewedBy: null,
    submittedAt: nowIso(),
    updatedAt: nowIso(),
    decidedAt: null,
    isNew: true,
  }
}

function store(request, user) {
  if (itemsOf(request).length === 0) {
    if (!request.isNew) request.status = S.CANCELLED
    persistRequests(requests)
    return null
  }
  request.revision += 1
  request.updatedAt = nowIso()
  request.submittedBy = actorDisplayName(user)
  if (request.isNew) {
    delete request.isNew
    requests.push(request)
  }
  persistRequests(requests)
  return request
}

function applyConsent(entity, body, user) {
  const before = snapshotOf(entity)
  setMarketingConsent(entity, body.marketingConsent)
  recordProfileChanges(entity, before, entity, { actor: user, isStaff: false })
}

/** The entity's section edit joins its pending request; marketing consent alone applies at once. */
export function proposeSection(entity, section, body, user) {
  const draft = applySection(section, snapshotOf(entity), body)
  assertLetterBacksPerson(section, draft, entity)
  if (section === 'contact') applyConsent(entity, body, user)
  const request = openFor(entity, user)
  for (const field of fieldsOfSection(section)) {
    const { read } = PROFILE_CHANGE_FIELDS[field]
    delete request.proposed[field]
    delete request.base[field]
    if (!sameValue(read(draft), read(entity))) {
      request.proposed[field] = read(draft)
      request.base[field] = read(entity)
    }
  }
  return store(request, user)
}

/** A new authorized person comes with a new authorization letter in the same request. */
function assertLetterBacksPerson(section, draft, entity) {
  const { read } = PROFILE_CHANGE_FIELDS.authorized_person_name
  if (section !== 'contact' || sameValue(read(draft), read(entity))) return
  const letter = pendingRequestOf(entity.id)?.files[F.AUTHORIZATION_LETTER]
  if (!letter?.upload) throw entityReject.authorizationLetterRequired()
}

function approvedFileOf(entity, kind) {
  return entity[ENTITY_KEY_OF_FILE[kind]]
}

export function mayHoldVatCertificate(entity) {
  const pending = pendingRequestOf(entity.id)
  return pending && 'vat_registered' in pending.proposed
    ? pending.proposed.vat_registered === true
    : entity.vatRegistered === true
}

export function proposeFile(entity, kind, upload, user) {
  const request = openFor(entity, user)
  request.files[kind] = { upload, removes: false }
  return store(request, user)
}

export function proposeFileRemoval(entity, kind, user) {
  const request = openFor(entity, user)
  if (approvedFileOf(entity, kind)) request.files[kind] = { upload: null, removes: true }
  else delete request.files[kind]
  return store(request, user)
}

export function cancelRequest(entity) {
  const pending = pendingRequestOf(entity.id)
  if (!pending) throw new MockRejection(404, 'profile.change_missing')
  pending.status = S.CANCELLED
  pending.updatedAt = nowIso()
  persistRequests(requests)
  return pending
}

export function staleFieldsOf(request, entity) {
  if (request.status !== S.PENDING) return []
  return Object.keys(request.proposed).filter(
    (field) => !sameValue(PROFILE_CHANGE_FIELDS[field].read(entity), request.base[field]),
  )
}

function assertItems(items, approved, reason) {
  if (approved.some((item) => !items.includes(item)))
    throw new MockRejection(422, 'profile.change_unknown_items')
  const split = PROFILE_CHANGE_COUPLED.some((group) => {
    const present = group.filter((field) => items.includes(field))
    return new Set(present.map((field) => approved.includes(field))).size > 1
  })
  if (split) throw new MockRejection(422, 'profile.change_coupled_items')
  const unbacked = Object.entries(PROFILE_CHANGE_NEEDS).some(
    ([item, partner]) => approved.includes(item) && !approved.includes(partner),
  )
  if (unbacked) throw new MockRejection(422, 'profile.change_authorization_letter_required')
  const rejects = items.some((item) => !approved.includes(item))
  if (rejects && String(reason ?? '').trim().length < REASON_MIN)
    throw new MockRejection(422, 'profile.change_reason_required')
}

function assertVatCertificateFits(request, entity, approved) {
  if (!approved.includes(F.VAT_CERTIFICATE) || !request.files[F.VAT_CERTIFICATE]?.upload) return
  const registered = approved.includes('vat_registered')
    ? request.proposed.vat_registered === true
    : entity.vatRegistered === true
  if (!registered) throw entityReject.vatNotRegistered()
}

function applyApproved(request, entity, approved, user) {
  const before = snapshotOf(entity)
  applyApprovedItems(entity, request, approved)
  recordProfileChanges(entity, before, entity, { actor: user, isStaff: false })
}

export function decideRequest(request, entity, decision, reviewer) {
  if (request.status !== S.PENDING) throw entityReject.illegalTransition()
  if (Number(decision.revision) !== request.revision)
    throw new MockRejection(409, 'profile.change_amended', { meta: { revision: request.revision } })
  const items = itemsOf(request)
  const approved = decision.approvedItems ?? []
  assertItems(items, approved, decision.reason)
  const stale = staleFieldsOf(request, entity).filter((field) => approved.includes(field))
  if (stale.length)
    throw new MockRejection(409, 'profile.change_stale', { meta: { staleFields: stale } })
  assertVatCertificateFits(request, entity, approved)
  applyApproved(request, entity, approved, reviewer)
  const decisions = Object.fromEntries(
    items.map((item) => [item, approved.includes(item) ? V.APPROVED : V.REJECTED]),
  )
  const rejectedCount = items.length - approved.length
  request.status =
    rejectedCount === 0 ? S.APPROVED : approved.length === 0 ? S.REJECTED : S.PARTIALLY_APPROVED
  request.decisions = decisions
  request.reviewNote = rejectedCount ? String(decision.reason).trim() : null
  request.reviewedBy = actorDisplayName(reviewer)
  request.decidedAt = nowIso()
  persistRequests(requests)
  return request
}

export function labelOf(item) {
  return { ...(PROFILE_FIELD_LABELS[item] ?? { ar: item, en: item }) }
}
