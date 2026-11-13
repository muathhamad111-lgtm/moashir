import { SAFE_LINK_PATTERNS } from '@/constants/cms'

export function isSafeLink(value) {
  return typeof value === 'string' && SAFE_LINK_PATTERNS.some((pattern) => pattern.test(value))
}
