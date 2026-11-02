const AA_NORMAL_TEXT = 4.5
const HEX_PATTERN = /^#([0-9a-f]{6})$/i

function channel(value) {
  const ratio = value / 255
  return ratio <= 0.03928 ? ratio / 12.92 : ((ratio + 0.055) / 1.055) ** 2.4
}

export function relativeLuminance(hex) {
  const match = HEX_PATTERN.exec(hex ?? '')
  if (!match) return null
  const digits = match[1]
  const [red, green, blue] = [0, 2, 4].map((start) =>
    channel(parseInt(digits.slice(start, start + 2), 16)),
  )
  return 0.2126 * red + 0.7152 * green + 0.0722 * blue
}

export function contrastRatio(foreground, background) {
  const first = relativeLuminance(foreground)
  const second = relativeLuminance(background)
  if (first === null || second === null) return null
  const lighter = Math.max(first, second)
  const darker = Math.min(first, second)
  return (lighter + 0.05) / (darker + 0.05)
}

export function meetsAA(ratio) {
  return typeof ratio === 'number' && ratio >= AA_NORMAL_TEXT
}
