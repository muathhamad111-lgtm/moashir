const PLACEHOLDER_FORMS =
  /\{\{[^{}\s]{1,40}\}\}|\{[^{}\s]{1,40}\}|\[[^[\]\s]{1,40}\]|%s|\bx{4,}\b/giu
const WHITESPACE = /\s+/gu
const PLACEHOLDER_TOKEN = '{}'

export function normalizedTemplate(text) {
  return String(text ?? '')
    .replace(PLACEHOLDER_FORMS, PLACEHOLDER_TOKEN)
    .replace(WHITESPACE, ' ')
    .trim()
}

export function matchesApprovedTemplate(text, approvedTexts = []) {
  const candidate = normalizedTemplate(text)
  return approvedTexts.some((approved) => normalizedTemplate(approved) === candidate)
}
