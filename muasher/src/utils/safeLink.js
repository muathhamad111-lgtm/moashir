import { HTTPS_LINK_PATTERN, SAFE_LINK_PATTERNS } from '@/constants/cms'

export function isSafeLink(value) {
  return typeof value === 'string' && SAFE_LINK_PATTERNS.some((pattern) => pattern.test(value))
}

export function isHttpsLink(value) {
  return typeof value === 'string' && HTTPS_LINK_PATTERN.test(value)
}
