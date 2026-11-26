import { STORED_ICON_PATTERN } from '@/constants/icons'
import { isSafeLink } from '@/utils/safeLink'
import { contentReject, fieldMessage } from './contentRejection'

export function hasLocalizedText(value) {
  return Boolean(value && (String(value.ar ?? '').trim() || String(value.en ?? '').trim()))
}

export function normalizeLocalized(value) {
  if (!hasLocalizedText(value)) return null
  return { ar: String(value.ar ?? '').trim(), en: String(value.en ?? '').trim() }
}

export function withFallback(value) {
  if (!hasLocalizedText(value)) return null
  return { ar: value.ar || value.en, en: value.en || value.ar }
}

export function createFieldErrors() {
  const fields = {}
  return {
    add(field, key, params) {
      fields[field] = [...(fields[field] ?? []), fieldMessage(key, params)]
    },
    check(condition, field, key, params) {
      if (!condition) this.add(field, key, params)
    },
    throwIfAny() {
      if (Object.keys(fields).length) throw contentReject.validation(fields)
    },
  }
}

export function checkLink(errors, body, field) {
  if (body[field] === undefined || body[field] === null || body[field] === '') return
  errors.check(isSafeLink(body[field]), field, 'linkSafe')
}

export function checkIcon(errors, body, field = 'icon') {
  if (!body[field]) return
  errors.check(STORED_ICON_PATTERN.test(body[field]), field, 'icon')
}
