import { EMPTY_SECRET } from '../../fixtures/integrations'

const LAST4_MIN_LENGTH = 20
const LAST4_LENGTH = 4

const VERDICT_MARKERS = Object.freeze([
  { marker: 'revoked', verdict: 'auth_failed' },
  { marker: 'inactive', verdict: 'account_inactive' },
])

export const SECRET_PATCH = Object.freeze({ KEEP: 'keep', REPLACE: 'replace', CLEAR: 'clear' })

export function patchKindOf(value) {
  if (value && typeof value === 'object' && value.clear === true) return SECRET_PATCH.CLEAR
  if (typeof value === 'string' && value.length > 0) return SECRET_PATCH.REPLACE
  return SECRET_PATCH.KEEP
}

function verdictOf(value) {
  return VERDICT_MARKERS.find(({ marker }) => value.includes(marker))?.verdict ?? 'ok'
}

function lastFourOf(value, showsLastFour) {
  return showsLastFour && value.length >= LAST4_MIN_LENGTH ? value.slice(-LAST4_LENGTH) : null
}

export function applySecretPatch(current, value, actor, { showsLastFour = true } = {}) {
  const kind = patchKindOf(value)
  if (kind === SECRET_PATCH.KEEP) return current
  if (kind === SECRET_PATCH.CLEAR) return { ...EMPTY_SECRET }
  return {
    set: true,
    last4: lastFourOf(value, showsLastFour),
    updatedAt: new Date().toISOString(),
    updatedBy: actor,
    verdict: verdictOf(value),
  }
}

export function secretMetaResource(meta) {
  return {
    set: meta?.set === true,
    last4: meta?.last4 ?? null,
    updatedAt: meta?.updatedAt ?? null,
    updatedBy: meta?.updatedBy ? { ...meta.updatedBy } : null,
  }
}

export function secretVerdict(meta) {
  return meta?.set ? meta.verdict : null
}

export function isSecretTouched(current, value) {
  const kind = patchKindOf(value)
  if (kind === SECRET_PATCH.REPLACE) return true
  return kind === SECRET_PATCH.CLEAR && current?.set === true
}
