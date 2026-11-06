import { certificatesFixture } from '../fixtures/certificates'

export const certificates = certificatesFixture.map((item) => ({ ...item }))

export function certificateResource(certificate) {
  const resource = { ...certificate }
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
