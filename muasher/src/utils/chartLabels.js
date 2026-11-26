const WRAP_AT = 14

export function splitLabel(text) {
  const value = String(text ?? '')
  if (value.length <= WRAP_AT || !value.includes(' ')) return [value]
  const words = value.split(' ')
  const middle = value.length / 2
  let first = ''
  for (const word of words) {
    if (first && first.length + word.length / 2 >= middle) break
    first = first ? `${first} ${word}` : word
  }
  return [first, value.slice(first.length).trim()].filter(Boolean)
}
