export const ENTITY_DOCUMENT_PURPOSES = Object.freeze({
  licenseCertificate: 'entity_license_certificate',
  authorizationLetter: 'entity_authorization_letter',
})

const UPLOADED_AT = '2026-06-28T06:15:00Z'
const SIZE_BYTES = 182272

function documentFile(id, purpose, name) {
  return Object.freeze({
    id,
    purpose,
    name,
    mimeType: 'application/pdf',
    sizeBytes: SIZE_BYTES,
    scanStatus: 'clean',
    createdAt: UPLOADED_AT,
  })
}

/** Al-Birr (5001) and Namaa (6001) carry both documents; every other demo entity has none, so it sees the notice. */
export const FIXTURE_ENTITY_DOCUMENT_FILES = Object.freeze({
  5001: {
    licenseCertificate: documentFile(
      '8c2d41a7-3b6e-4f10-a5c2-0000000070a1',
      ENTITY_DOCUMENT_PURPOSES.licenseCertificate,
      'license-certificate-albirr.pdf',
    ),
    authorizationLetter: documentFile(
      '8c2d41a7-3b6e-4f10-a5c2-0000000070a2',
      ENTITY_DOCUMENT_PURPOSES.authorizationLetter,
      'authorization-letter-albirr.pdf',
    ),
  },
  6001: {
    licenseCertificate: documentFile(
      '8c2d41a7-3b6e-4f10-a5c2-0000000070b1',
      ENTITY_DOCUMENT_PURPOSES.licenseCertificate,
      'license-certificate-namaa.pdf',
    ),
    authorizationLetter: documentFile(
      '8c2d41a7-3b6e-4f10-a5c2-0000000070b2',
      ENTITY_DOCUMENT_PURPOSES.authorizationLetter,
      'authorization-letter-namaa.pdf',
    ),
  },
})

function descriptorOf(file) {
  return file
    ? { id: file.id, name: file.name, sizeBytes: file.sizeBytes, uploadedAt: file.createdAt }
    : null
}

export function documentsOf(entityId) {
  const files = FIXTURE_ENTITY_DOCUMENT_FILES[entityId] ?? {}
  return {
    licenseCertificate: descriptorOf(files.licenseCertificate),
    authorizationLetter: descriptorOf(files.authorizationLetter),
  }
}

export function entityDocumentFilesSeed() {
  return Object.values(FIXTURE_ENTITY_DOCUMENT_FILES).flatMap((files) =>
    Object.values(files).map((file) => ({ ...file })),
  )
}
