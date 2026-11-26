import { certificatesFixture } from '../fixtures/certificates'
import { riyadhDateKey } from '@/utils/datetime'
import { scenarioValidUntil } from './certificateScenarios'

export const certificates = certificatesFixture.map((item) => ({ ...item }))

const END_OF_DAY_RIYADH = 'T23:59:59+03:00'
const EXPIRING_SOON_DAYS = 30
const DAY_MS = 86_400_000
const ISSUED = 'issued'
const CLOSED_VALIDITY = Object.freeze({ validity: null, daysUntilExpiry: null })

function calendarDaysUntil(dateKey) {
  const today = Date.parse(`${riyadhDateKey(new Date())}T00:00:00Z`)
  return Math.round((Date.parse(`${dateKey}T00:00:00Z`) - today) / DAY_MS)
}

export function validityStateOf(certificate, validUntil) {
  if (certificate.status !== ISSUED) return CLOSED_VALIDITY
  if (!validUntil) return { validity: 'valid', daysUntilExpiry: null }
  const days = calendarDaysUntil(validUntil)
  if (days < 0) return { validity: 'expired', daysUntilExpiry: null }
  return { validity: days <= EXPIRING_SOON_DAYS ? 'expiring_soon' : 'valid', daysUntilExpiry: days }
}

function validityOf(certificate) {
  const validUntil = scenarioValidUntil(certificate) ?? certificate.validUntil ?? null
  const validUntilAt = validUntil
    ? new Date(`${validUntil}${END_OF_DAY_RIYADH}`).toISOString()
    : null
  return {
    validUntil,
    validUntilAt,
    isExpired: validUntilAt !== null && Date.now() >= Date.parse(validUntilAt),
    ...validityStateOf(certificate, validUntil),
  }
}

export function certificateResource(certificate) {
  const resource = { ...certificate, ...validityOf(certificate) }
  delete resource.entityId
  return resource
}

export function certificateByCode(code) {
  const normalized = String(code ?? '')
    .replace(/[\s-]+/g, '')
    .toUpperCase()
  return (
    certificates.find(
      (certificate) => certificate.verificationCode.replaceAll('-', '') === normalized,
    ) ?? null
  )
}
