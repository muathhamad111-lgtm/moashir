import { CONTACT_ENQUIRY_TYPES, CONTACT_LIMITS, CONTACT_MOBILE_PATTERN } from '@/constants/contact'
import { MockRejection } from '../contract/rejection'

const WINDOW_MS = 10 * 60 * 1000
const WINDOW_LIMIT = 5
const FIRST_SEQUENCE = 14
const SEQUENCE_DIGITS = 6
const RETRY_AFTER_SECONDS = 600
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

const state = { sequence: FIRST_SEQUENCE, attempts: [], stored: [] }

function text(value) {
  return typeof value === 'string' ? value.trim() : ''
}

function lengthIssue(value, { min = 0, max }) {
  if (value.length < min) return { key: 'min', params: { min } }
  if (value.length > max) return { key: 'max', params: { max } }
  return null
}

function identityIssues(body) {
  const issues = {}
  const name = text(body.name)
  const email = text(body.email)
  if (!name) issues.name = { key: 'required' }
  else {
    const nameIssue = lengthIssue(name, {
      min: CONTACT_LIMITS.nameMin,
      max: CONTACT_LIMITS.nameMax,
    })
    if (nameIssue) issues.name = nameIssue
  }
  if (!email) issues.email = { key: 'required' }
  else if (!EMAIL_PATTERN.test(email) || email.length > CONTACT_LIMITS.emailMax)
    issues.email = { key: 'email' }
  if (text(body.organization).length > CONTACT_LIMITS.organizationMax)
    issues.organization = { key: 'max', params: { max: CONTACT_LIMITS.organizationMax } }
  return issues
}

function messageIssues(body) {
  const issues = {}
  const mobile = text(body.mobile)
  if (mobile && !CONTACT_MOBILE_PATTERN.test(mobile)) issues.mobile = { key: 'mobile' }
  if (!CONTACT_ENQUIRY_TYPES.includes(body.enquiryType)) issues.enquiryType = { key: 'enquiry' }
  const message = text(body.message)
  if (!message) issues.message = { key: 'required' }
  else {
    const messageIssue = lengthIssue(message, {
      min: CONTACT_LIMITS.messageMin,
      max: CONTACT_LIMITS.messageMax,
    })
    if (messageIssue) issues.message = messageIssue
  }
  if (body.privacyConsent !== true) issues.privacyConsent = { key: 'consent' }
  return issues
}

function referenceOf(sequence, now) {
  return `CNT-${now.getUTCFullYear()}-${String(sequence).padStart(SEQUENCE_DIGITS, '0')}`
}

function guardRate(now) {
  state.attempts = state.attempts.filter((time) => now - time < WINDOW_MS)
  if (state.attempts.length >= WINDOW_LIMIT) {
    throw new MockRejection(429, 'rate_limited', {
      headers: { 'Retry-After': String(RETRY_AFTER_SECONDS) },
    })
  }
  state.attempts.push(now)
}

export function contactIssuesOf(body, { signedIn }) {
  return { ...(signedIn ? {} : identityIssues(body)), ...messageIssues(body) }
}

export function submitContactMessage(body, { signedIn, now = new Date() }) {
  guardRate(now.getTime())
  const issues = contactIssuesOf(body, { signedIn })
  if (Object.keys(issues).length) {
    throw new MockRejection(422, 'validation_failed', { fields: issues })
  }
  const reference = referenceOf(state.sequence, now)
  state.sequence += 1
  if (text(body.website)) return { reference, createdAt: now.toISOString() }
  state.stored.push({ reference, enquiryType: body.enquiryType, signedIn })
  return { reference, createdAt: now.toISOString() }
}

export function resetContactMessages() {
  state.sequence = FIRST_SEQUENCE
  state.attempts = []
  state.stored = []
}

export function storedContactMessages() {
  return [...state.stored]
}
