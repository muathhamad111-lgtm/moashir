const PLACEHOLDER = /\{([a-z_]+)\}/g
const LATIN_TEXT = /^[\x20-\x7E\r\n]*$/
const SINGLE_SEGMENT = { latin: 160, unicode: 70 }
const LONGEST_SAMPLES = Object.freeze({
  latin: Object.freeze({ code: '00000000', validity: '15 minutes', minutes: '15' }),
  unicode: Object.freeze({ code: '00000000', validity: 'دقيقة واحدة', minutes: '15' }),
})

function usedPlaceholders(body) {
  return [...String(body ?? '').matchAll(PLACEHOLDER)].map((match) => match[1])
}

function filled(body) {
  const text = String(body ?? '')
  const samples = LATIN_TEXT.test(text) ? LONGEST_SAMPLES.latin : LONGEST_SAMPLES.unicode
  return text.replace(PLACEHOLDER, (token, name) => samples[name] ?? token)
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
