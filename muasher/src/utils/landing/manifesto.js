const SEPARATORS = /[،,]+/
const PUNCTUATION = /[.،,؛;:!؟?«»"'()]/g

function normalized(word) {
  return word.replace(PUNCTUATION, '').trim()
}

export function keyWordSet(source) {
  return new Set(
    String(source ?? '')
      .split(SEPARATORS)
      .flatMap((part) => part.trim().split(/\s+/))
      .map(normalized)
      .filter(Boolean),
  )
}

export function manifestoWords(text, keyWords) {
  const keys = keyWordSet(keyWords)
  return String(text ?? '')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .map((word, index) => ({ key: index, word, isKey: keys.has(normalized(word)) }))
}
