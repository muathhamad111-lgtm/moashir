const KEBAB_TOKEN = /^[a-z][a-z0-9]*(?:-[a-z0-9]+)+$/
const SNAKE_TOKEN = /^[a-z][a-z0-9]*(?:_[a-z0-9]+)+$/

export function toCanonicalStatus(token) {
  return typeof token === 'string' && KEBAB_TOKEN.test(token) ? token.replaceAll('-', '_') : token
}

export function fromCanonicalStatus(value) {
  return typeof value === 'string' && SNAKE_TOKEN.test(value) ? value.replaceAll('_', '-') : value
}

export function statusLabelKey(token) {
  return `status.${toCanonicalStatus(token)}`
}
