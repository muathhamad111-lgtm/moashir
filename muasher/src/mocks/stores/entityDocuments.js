import { ENTITY_DOCUMENT_RULES } from '@/constants/entityDocuments'
import { PROFILE_CHANGE_FILES as F } from '@/constants/entityEnums'
import { ENTITY_DOCUMENT_PURPOSES } from '../fixtures/entityDocuments'
import { entityReject, fieldError } from './entityRejection'
import { findFile } from './finance/financeState'
import { storeFile } from './finance/signedFiles'
import { nowIso } from './finance/paymentLifecycle'

export const REQUIRED_DOCUMENT_KEYS = Object.freeze(['licenseCertificate', 'authorizationLetter'])

const KEY_OF_SEGMENT = Object.freeze({
  license_certificate: 'licenseCertificate',
  authorization_letter: 'authorizationLetter',
  vat_certificate: 'vatCertificate',
})

const CHANGE_KIND_OF_KEY = Object.freeze({
  licenseCertificate: F.LICENSE_CERTIFICATE,
  authorizationLetter: F.AUTHORIZATION_LETTER,
})

const LIMIT = { max: ENTITY_DOCUMENT_RULES.maxMb }

function isFile(value) {
  return Boolean(value) && typeof value === 'object' && typeof value.arrayBuffer === 'function'
}

function documentIssue(file) {
  if (!isFile(file)) return 'field.required'
  if (!ENTITY_DOCUMENT_RULES.types.includes(file.type)) return 'field.document_type'
  if (file.size > ENTITY_DOCUMENT_RULES.maxBytes) return 'field.document_size'
  return null
}

async function storedDocument(key, file) {
  const stored = storeFile({
    purpose: ENTITY_DOCUMENT_PURPOSES[key],
    name: file.name || `${key}.pdf`,
    mimeType: file.type,
    sizeBytes: file.size,
    content: await file.arrayBuffer(),
  })
  return { id: stored.id, name: stored.name, sizeBytes: stored.sizeBytes, uploadedAt: nowIso() }
}

function descriptorOf(document) {
  if (!document) return null
  const file = findFile(document.id)
  return {
    id: document.id,
    name: document.name,
    mimeType: file?.mimeType ?? ENTITY_DOCUMENT_RULES.types[0],
    sizeBytes: document.sizeBytes,
    uploadedAt: document.uploadedAt,
  }
}

/** Mirrors the API: both documents are required at registration, PDF only, within the size limit. */
export function checkRegistrationDocuments(errors, body) {
  REQUIRED_DOCUMENT_KEYS.forEach((key) => {
    const issue = documentIssue(body[key])
    if (issue) errors.add(key, issue, LIMIT)
  })
}

export async function attachRegistrationDocuments(entity, body) {
  for (const key of REQUIRED_DOCUMENT_KEYS) entity[key] = await storedDocument(key, body[key])
  return entity
}

export async function acceptEntityDocument(key, file) {
  const issue = documentIssue(file)
  if (issue) throw entityReject.validation({ file: [fieldError(issue, LIMIT)] })
  return storedDocument(key, file)
}

/**
 * Mirrors the API's staff edit (operator 2026-11-25): a new authorized person needs a new authorization letter, and a
 * letter sent with the edit replaces the current one. Answers the stored letter, or null when none was sent.
 */
export async function acceptStaffLetter(entity, body) {
  const file = body.authorizationLetter
  const personChanged =
    (body.authorizedPersonName ?? '').trim() !== (entity.owner?.name ?? '').trim()
  if (!isFile(file)) {
    if (personChanged) throw entityReject.authorizationLetterRequired()
    return null
  }
  const issue = documentIssue(file)
  if (issue) throw entityReject.validation({ authorizationLetter: [fieldError(issue, LIMIT)] })
  return storedDocument('authorizationLetter', file)
}

export function documentKeyOf(segment, { uploadable = false } = {}) {
  const key = KEY_OF_SEGMENT[segment]
  if (!key || (uploadable && !REQUIRED_DOCUMENT_KEYS.includes(key))) throw entityReject.notFound()
  return key
}

export function changeKindOf(key) {
  return CHANGE_KIND_OF_KEY[key]
}

export function entityDocumentFileOf(entity, key) {
  const file = entity[key] ? findFile(entity[key].id) : null
  if (!file) throw entityReject.documentMissing()
  return file
}

export function entityDocumentsOf(entity) {
  return {
    licenseCertificate: descriptorOf(entity.licenseCertificate),
    authorizationLetter: descriptorOf(entity.authorizationLetter),
    vatCertificate: descriptorOf(entity.vatCertificate),
  }
}
