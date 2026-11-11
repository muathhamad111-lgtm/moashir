import { certificatesFixture } from '../fixtures/certificates'

export const certificates = certificatesFixture.map((item) => ({ ...item }))

const END_OF_DAY_RIYADH = 'T23:59:59+03:00'

function validityOf(certificate) {
  const validUntil = certificate.validUntil ?? null
  const validUntilAt = validUntil
    ? new Date(`${validUntil}${END_OF_DAY_RIYADH}`).toISOString()
    : null
  return {
    validUntil,
    validUntilAt,
    isExpired: validUntilAt !== null && Date.now() >= Date.parse(validUntilAt),
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
