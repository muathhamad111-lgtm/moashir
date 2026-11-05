const PLACEHOLDER = /\{([a-z_]+)\}/g
const LATIN_TEXT = /^[\x20-\x7E\r\n]*$/
const SINGLE_SEGMENT = { latin: 160, unicode: 70 }
const LONGEST_SAMPLE = Object.freeze({ code: '00000000', minutes: '15' })

function usedPlaceholders(body) {
  return [...String(body ?? '').matchAll(PLACEHOLDER)].map((match) => match[1])
}

function filled(body) {
  return String(body ?? '').replace(PLACEHOLDER, (token, name) => LONGEST_SAMPLE[name] ?? token)
}

function fitsOneSegment(text) {
  const limit = LATIN_TEXT.test(text) ? SINGLE_SEGMENT.latin : SINGLE_SEGMENT.unicode
  return [...text].length <= limit
}

export function templateRuleError(definition, body) {
  const used = usedPlaceholders(body)
  if (used.some((name) => !definition.placeholders.includes(name))) return 'unknown_placeholder'
  if (definition.requiredPlaceholders.some((name) => !used.includes(name)))
    return 'missing_placeholder'
  if (definition.singleSegment && !fitsOneSegment(filled(body))) return 'too_long'
  return null
}
