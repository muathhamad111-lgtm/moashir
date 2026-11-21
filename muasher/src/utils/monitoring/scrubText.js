export const REDACTED = '[redacted]'

const TOKEN_PATHS = [
  /(\/auth\/invitations\/)[^/?#\s"']+/gi,
  /(\/auth\/reset-password\/)[^/?#\s"']+/gi,
]

const LONG_TOKEN_SEGMENT = /(?<=\/)[A-Za-z0-9]{32,}(?=[/?#\s"']|$)/g

const CREDENTIAL_PATTERNS = [
  /\bBearer\s+[A-Za-z0-9._~+/=-]+/gi,
  /\b(?:sk|pk)_(?:test|live)_[A-Za-z0-9]+/g,
  /\bsk-[A-Za-z0-9_-]{16,}/g,
]

const CREDENTIAL_FIELDS =
  /(["']?(?:apiKey|api_key|secret_token|shared_secret|secret_key|webhook_secret|client_secret|x-api-key|authorization|password|token)["']?\s*[:=]\s*)("[^"]*"|'[^']*'|[^\s,&}]+)/gi

const PERSONAL_PATTERNS = [
  /[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi,
  /\+?9665\d{8}\b/g,
  /\b05\d{8}\b/g,
  /\b[12]\d{9}\b/g,
  /[\p{L}\p{N}_\-. ]+\.(?:pdf|docx?|xlsx?|pptx?|jpe?g|png|csv)\b/giu,
]

export function redactSensitiveUrl(text) {
  const withoutTokens = TOKEN_PATHS.reduce(
    (value, pattern) => value.replace(pattern, `$1${REDACTED}`),
    text,
  )
  return withoutTokens.replace(LONG_TOKEN_SEGMENT, REDACTED)
}

export function scrubText(value) {
  if (typeof value !== 'string' || !value) return value
  const withoutCredentials = CREDENTIAL_PATTERNS.reduce(
    (text, pattern) => text.replace(pattern, REDACTED),
    redactSensitiveUrl(value),
  ).replace(CREDENTIAL_FIELDS, `$1${REDACTED}`)
  return PERSONAL_PATTERNS.reduce(
    (text, pattern) => text.replace(pattern, REDACTED),
    withoutCredentials,
  )
}

export function scrubUrl(value) {
  if (typeof value !== 'string' || !value) return value
  return scrubText(value.split(/[?#]/)[0])
}
