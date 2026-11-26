const EXTERNAL = /^https:\/\//
const MAIL = /^mailto:/
const ANCHOR = /^\/?#([A-Za-z][\w-]*)$/

export function anchorOf(url) {
  return String(url ?? '').match(ANCHOR)?.[1] ?? ''
}

export function linkKind(url) {
  const value = String(url ?? '')
  if (anchorOf(value)) return 'anchor'
  if (EXTERNAL.test(value)) return 'external'
  if (MAIL.test(value)) return 'mail'
  if (value.startsWith('/')) return 'route'
  return 'none'
}
