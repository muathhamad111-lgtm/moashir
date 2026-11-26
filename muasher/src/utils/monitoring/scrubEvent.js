import { REDACTED, scrubText, scrubUrl } from './scrubText'

const SAFE_HEADERS = ['accept', 'accept-language', 'content-type', 'user-agent', 'x-request-id']
const SENSITIVE_KEY =
  /pass|token|secret|code|otp|email|mail|phone|mobile|national|unified|name|body|message|reason|answer|note|value|query|search|cookie|card|iban|file/i
const URL_KEY = /(^|\.)(url|from|to)$/i
const DROPPED_DATA_KEYS = [
  'arguments',
  'body',
  'input',
  'response',
  'request_body',
  'http.query',
  'http.fragment',
  'http.request.body',
  'http.response.body',
]
const MAX_DEPTH = 4

function scrubValue(value, depth) {
  if (typeof value === 'string') return scrubText(value)
  if (Array.isArray(value))
    return depth >= MAX_DEPTH ? [] : value.map((item) => scrubValue(item, depth + 1))
  if (value && typeof value === 'object') return scrubObject(value, depth + 1)
  return value
}

function scrubEntry(key, value, depth) {
  if (SENSITIVE_KEY.test(key) && (value === null || typeof value !== 'object')) return REDACTED
  return scrubValue(value, depth)
}

export function scrubObject(object, depth = 0) {
  if (!object || typeof object !== 'object' || depth > MAX_DEPTH) return {}
  return Object.fromEntries(
    Object.entries(object).map(([key, value]) => [key, scrubEntry(key, value, depth)]),
  )
}

function scrubHeaders(headers = {}) {
  return Object.fromEntries(
    Object.entries(headers).filter(([name]) => SAFE_HEADERS.includes(name.toLowerCase())),
  )
}

function scrubRequest(request) {
  if (!request) return request
  return {
    method: request.method,
    url: scrubUrl(request.url),
    headers: scrubHeaders(request.headers),
  }
}

export function scrubData(data) {
  if (!data || typeof data !== 'object') return data
  return Object.fromEntries(
    Object.entries(data)
      .filter(([key]) => !DROPPED_DATA_KEYS.includes(key))
      .map(([key, value]) => [key, URL_KEY.test(key) ? scrubUrl(value) : scrubValue(value, 1)]),
  )
}

export function scrubBreadcrumb(breadcrumb) {
  if (!breadcrumb) return breadcrumb
  return {
    ...breadcrumb,
    message: scrubText(breadcrumb.message),
    data: scrubData(breadcrumb.data),
  }
}

function scrubException(exception) {
  if (!Array.isArray(exception?.values)) return exception
  return {
    ...exception,
    values: exception.values.map((entry) => ({ ...entry, value: scrubText(entry.value) })),
  }
}

function scrubSpans(spans) {
  if (!Array.isArray(spans)) return spans
  return spans.map((span) => ({
    ...span,
    description: scrubUrl(span.description),
    data: scrubData(span.data),
  }))
}

function withoutUser(event) {
  return Object.fromEntries(Object.entries(event).filter(([key]) => key !== 'user'))
}

export function scrubEvent(event) {
  if (!event) return event
  return {
    ...withoutUser(event),
    message: scrubText(event.message),
    transaction: scrubUrl(event.transaction),
    request: scrubRequest(event.request),
    exception: scrubException(event.exception),
    extra: scrubObject(event.extra),
    tags: scrubObject(event.tags),
    breadcrumbs: Array.isArray(event.breadcrumbs)
      ? event.breadcrumbs.map(scrubBreadcrumb)
      : event.breadcrumbs,
    spans: scrubSpans(event.spans),
  }
}

export const SENTRY_SCRUBBING = Object.freeze({
  sendDefaultPii: false,
  beforeSend: scrubEvent,
  beforeSendTransaction: scrubEvent,
  beforeBreadcrumb: scrubBreadcrumb,
})
