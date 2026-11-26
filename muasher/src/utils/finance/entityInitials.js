// Two letters for an entity's avatar, skipping a leading organisation word and the Arabic article.
const LEADING_WORDS = /^(جمعية|مؤسسة|وقف|مركز|association|foundation|society|endowment|the)\s+/i
const ARABIC_ARTICLE = /^ال(?=\S{2})/

export function entityInitials(name) {
  const words = String(name ?? '')
    .trim()
    .replace(LEADING_WORDS, '')
    .split(/\s+/)
    .filter(Boolean)
  return words
    .slice(0, 2)
    .map((word) => word.replace(ARABIC_ARTICLE, '').charAt(0).toLocaleUpperCase())
    .join('')
}
