import { legalVersionsFixture } from '../fixtures/legalVersions'
import { MockRejection } from '../contract/rejection'
import { hasMarkup } from '@/utils/legal/markdownBlocks'

const DRAFT = 'draft'
const CURRENT = 'current'
const SUPERSEDED = 'superseded'
const VERSION_PATTERN = /^[A-Za-z0-9._-]{1,20}$/
const LOCALES = ['ar', 'en']
const STATUS_ORDER = { draft: 0, current: 1, superseded: 2 }

const state = { versions: [], nextId: 40, publishedInSession: new Set() }

export function resetLegalVersions() {
  state.versions = structuredClone(legalVersionsFixture)
  state.nextId = 40
  state.publishedInSession = new Set()
}

resetLegalVersions()

function summary(version) {
  const copy = { ...version }
  delete copy.body
  return copy
}

export function versionsOf(kind) {
  return state.versions
    .filter((version) => version.kind === kind)
    .sort(
      (left, right) =>
        STATUS_ORDER[left.status] - STATUS_ORDER[right.status] ||
        String(right.publishedAt).localeCompare(String(left.publishedAt)),
    )
    .map(summary)
}

export function legalVersion(id) {
  const version = state.versions.find((item) => item.id === Number(id))
  if (!version) throw new MockRejection(404, 'not_found')
  return version
}

function fieldError(field, ar, en) {
  return { [field]: [{ ar, en }] }
}

function validated(input, kind, ownId = null) {
  const fields = {}
  if (input.version !== undefined) {
    if (!VERSION_PATTERN.test(input.version ?? ''))
      Object.assign(
        fields,
        fieldError(
          'version',
          'صيغة رقم الإصدار غير صحيحة.',
          'The version label format is invalid.',
        ),
      )
    else if (
      state.versions.some(
        (item) => item.kind === kind && item.version === input.version && item.id !== ownId,
      )
    )
      throw new MockRejection(422, 'legal.version_taken', {
        fields: fieldError(
          'version',
          'هذا الإصدار مستخدم من قبل.',
          'This version label is already used.',
        ),
      })
  }
  LOCALES.forEach((locale) => {
    if (input.title && !String(input.title[locale] ?? '').trim())
      Object.assign(
        fields,
        fieldError(`title.${locale}`, 'العنوان مطلوب.', 'The title is required.'),
      )
    if (input.body && !String(input.body[locale] ?? '').trim())
      Object.assign(fields, fieldError(`body.${locale}`, 'النص مطلوب.', 'The text is required.'))
    if (input.body && hasMarkup(input.body[locale]))
      Object.assign(
        fields,
        fieldError(
          `body.${locale}`,
          'لا يُقبل وسم HTML في النص؛ استخدم تنسيق Markdown المبسّط.',
          'HTML markup is not accepted; use the simple Markdown format.',
        ),
      )
  })
  if (Object.keys(fields).length) throw new MockRejection(422, 'validation_failed', { fields })
  return input
}

function draftOnly(version) {
  if (version.status !== DRAFT)
    throw new MockRejection(409, 'legal.version_not_draft', { fields: { versionStatus: [] } })
  return version
}

export function createDraft(kind, input) {
  if (state.versions.some((item) => item.kind === kind && item.status === DRAFT))
    throw new MockRejection(409, 'legal.draft_exists')
  const body = validated(input, kind)
  state.nextId += 1
  const now = new Date().toISOString()
  const draft = {
    id: state.nextId,
    kind,
    version: body.version,
    status: DRAFT,
    title: { ...body.title },
    body: { ...body.body },
    effectiveFrom: null,
    publishedAt: null,
    createdAt: now,
    updatedAt: now,
  }
  state.versions.push(draft)
  return draft
}

export function updateDraft(id, input) {
  const draft = draftOnly(legalVersion(id))
  const changes = validated(input, draft.kind, draft.id)
  Object.assign(draft, {
    ...(changes.version !== undefined ? { version: changes.version } : {}),
    ...(changes.title ? { title: { ...changes.title } } : {}),
    ...(changes.body ? { body: { ...changes.body } } : {}),
    updatedAt: new Date().toISOString(),
  })
  return draft
}

export function discardDraft(id) {
  const draft = draftOnly(legalVersion(id))
  state.versions = state.versions.filter((item) => item !== draft)
}

export function publishDraft(id) {
  const draft = draftOnly(legalVersion(id))
  const now = new Date().toISOString()
  state.versions
    .filter((item) => item.kind === draft.kind && item.status === CURRENT)
    .forEach((item) => Object.assign(item, { status: SUPERSEDED }))
  Object.assign(draft, {
    status: CURRENT,
    publishedAt: now,
    effectiveFrom: now.slice(0, 10),
    updatedAt: now,
  })
  state.publishedInSession.add(draft.kind)
  return draft
}

export function sessionPublication(kind) {
  if (!state.publishedInSession.has(kind)) return null
  return state.versions.find((item) => item.kind === kind && item.status === CURRENT) ?? null
}
