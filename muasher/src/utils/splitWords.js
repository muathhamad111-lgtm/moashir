export function splitWords(text) {
  return String(text ?? '')
    .split(/\s+/)
    .filter(Boolean)
}
