import { STORED_ICON_PATTERN, STORED_ICON_STYLE } from '@/constants/icons'
import { LEGACY_ICON_NAMES } from './legacyIconNames'

const LEGACY_NAME_START = ' fa-'

export function iconNameFromStored(value) {
  if (typeof value !== 'string') return ''
  const trimmed = value.trim()
  if (!STORED_ICON_PATTERN.test(trimmed)) return trimmed
  const legacyName = trimmed.slice(trimmed.indexOf(LEGACY_NAME_START) + LEGACY_NAME_START.length)
  return LEGACY_ICON_NAMES[legacyName] ?? legacyName
}

export function storedIconFor(name) {
  const iconName = typeof name === 'string' ? name.trim() : ''
  return iconName ? `${STORED_ICON_STYLE} fa-${iconName}` : null
}
