export const CONTACT_ENQUIRY_TYPES = Object.freeze([
  'general',
  'self_assessment',
  'accredited_assessment',
  'payments',
  'technical_support',
  'privacy_request',
  'other',
])

export const CONTACT_LIMITS = Object.freeze({
  nameMin: 2,
  nameMax: 150,
  emailMax: 254,
  organizationMax: 200,
  messageMin: 10,
  messageMax: 5000,
})

export const CONTACT_MOBILE_PATTERN = /^(05\d{8}|\+9665\d{8})$/

export const CONTACT_HONEYPOT = 'website'

export const CONTACT_STATUS = Object.freeze({
  idle: 'idle',
  submitted: 'submitted',
  rateLimited: 'rate-limited',
})
