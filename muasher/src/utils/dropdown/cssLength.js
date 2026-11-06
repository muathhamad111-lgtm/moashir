const DEFAULT_ROOT_FONT_SIZE = 16
const LENGTH_PATTERN = /^(-?\d*\.?\d+)(px|rem)$/

export function rootFontSize() {
  if (typeof document === 'undefined') return DEFAULT_ROOT_FONT_SIZE
  const size = Number.parseFloat(getComputedStyle(document.documentElement).fontSize)
  return Number.isFinite(size) && size > 0 ? size : DEFAULT_ROOT_FONT_SIZE
}

export function toPixels(length, remSize = DEFAULT_ROOT_FONT_SIZE) {
  const match = LENGTH_PATTERN.exec(String(length).trim())
  if (!match) return 0
  const value = Number(match[1])
  return match[2] === 'rem' ? value * remSize : value
}
