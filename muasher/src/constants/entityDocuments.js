export const ENTITY_DOCUMENTS = Object.freeze({
  LICENSE_CERTIFICATE: 'licenseCertificate',
  AUTHORIZATION_LETTER: 'authorizationLetter',
  VAT_CERTIFICATE: 'vatCertificate',
})

/** The documents every entity gives at registration and keeps current through its profile. */
export const REQUIRED_ENTITY_DOCUMENTS = Object.freeze([
  ENTITY_DOCUMENTS.LICENSE_CERTIFICATE,
  ENTITY_DOCUMENTS.AUTHORIZATION_LETTER,
])

export const STAFF_ENTITY_DOCUMENTS = Object.freeze([
  ...REQUIRED_ENTITY_DOCUMENTS,
  ENTITY_DOCUMENTS.VAT_CERTIFICATE,
])

export const ENTITY_DOCUMENT_RULES = Object.freeze({
  types: Object.freeze(['application/pdf']),
  accept: '.pdf,application/pdf',
  maxBytes: 5 * 1024 * 1024,
  maxMb: 5,
})

export const PDF_MIME_TYPE = 'application/pdf'
