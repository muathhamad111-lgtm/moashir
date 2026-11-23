import { BILLING_PATTERNS, BILLING_TEXT_LIMITS } from '@/constants/entityProfile'
import { blank } from './entityValidation'
import { entityReject, fieldError } from './entityRejection'
import { storeFile } from './finance/signedFiles'
import { nowIso } from './finance/paymentLifecycle'

const VAT_CERTIFICATE_MAX_BYTES = 5 * 1024 * 1024

const ADDRESS_KEYS = Object.freeze([
  'addressBuildingNumber',
  'addressStreet',
  'addressDistrict',
  'addressCity',
  'addressPostalCode',
  'addressAdditionalNumber',
])

export const BILLING_KEYS = Object.freeze([
  ...ADDRESS_KEYS,
  'addressShortCode',
  'vatRegistered',
  'vatNumber',
])

const REQUIRED_TEXT = Object.freeze({
  addressStreet: BILLING_TEXT_LIMITS.STREET,
  addressDistrict: BILLING_TEXT_LIMITS.DISTRICT,
  addressCity: BILLING_TEXT_LIMITS.CITY,
})

const PATTERNED = Object.freeze([
  ['addressBuildingNumber', BILLING_PATTERNS.BUILDING_NUMBER, 'field.building_number', true],
  ['addressPostalCode', BILLING_PATTERNS.POSTAL_CODE, 'field.postal_code', true],
  ['addressAdditionalNumber', BILLING_PATTERNS.ADDITIONAL_NUMBER, 'field.additional_number', true],
  ['addressShortCode', BILLING_PATTERNS.SHORT_CODE, 'field.short_code', false],
])

function checkPatterned(errors, body) {
  PATTERNED.forEach(([key, pattern, code, required]) => {
    if (blank(body[key])) {
      if (required) errors.add(key, 'field.required')
      return
    }
    if (!pattern.test(String(body[key]))) errors.add(key, code)
  })
}

function checkText(errors, body) {
  Object.entries(REQUIRED_TEXT).forEach(([key, max]) => {
    if (blank(body[key])) errors.add(key, 'field.required')
    else if (String(body[key]).trim().length > max) errors.add(key, 'field.max_chars', { max })
  })
}

function checkVat(errors, body) {
  if (typeof body.vatRegistered !== 'boolean') {
    errors.add('vatRegistered', 'field.required')
    return
  }
  if (!body.vatRegistered) {
    if (!blank(body.vatNumber)) errors.add('vatNumber', 'field.vat_number_prohibited')
    return
  }
  if (blank(body.vatNumber)) errors.add('vatNumber', 'field.vat_number_required')
  else if (!BILLING_PATTERNS.VAT_NUMBER.test(String(body.vatNumber)))
    errors.add('vatNumber', 'field.vat_number')
}

export function checkBilling(errors, body) {
  checkPatterned(errors, body)
  checkText(errors, body)
  checkVat(errors, body)
}

function trimmed(value) {
  return blank(value) ? null : String(value).trim()
}

export function applyBilling(entity, body) {
  ADDRESS_KEYS.forEach((key) => {
    entity[key] = trimmed(body[key])
  })
  entity.addressShortCode = trimmed(body.addressShortCode)?.toUpperCase() ?? null
  entity.vatRegistered = body.vatRegistered
  entity.vatNumber = body.vatRegistered ? trimmed(body.vatNumber) : null
  if (!body.vatRegistered) entity.vatCertificate = null
}

function refuse(code) {
  return entityReject.validation({ file: [fieldError(code)] })
}

/** Checks and stores an uploaded VAT certificate; the caller decides where it goes (a pending change request). */
export async function acceptVatCertificate(file) {
  if (!file || typeof file === 'string') throw refuse('field.required')
  if (file.type !== 'application/pdf') throw refuse('field.vat_certificate_type')
  if (file.size > VAT_CERTIFICATE_MAX_BYTES) throw refuse('field.vat_certificate_size')
  const stored = storeFile({
    purpose: 'vat_certificate',
    name: file.name || 'vat-certificate.pdf',
    mimeType: file.type,
    sizeBytes: file.size,
    content: await file.arrayBuffer(),
  })
  return {
    id: stored.id,
    name: stored.name,
    sizeBytes: stored.sizeBytes,
    uploadedAt: nowIso(),
  }
}

export function vatCertificateFileOf(entity, findFile) {
  const file = entity.vatCertificate ? findFile(entity.vatCertificate.id) : null
  if (!file) throw entityReject.vatCertificateMissing()
  return file
}
