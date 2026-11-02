export function slugify(source) {
  return String(source ?? '')
    .toLowerCase()
    .trim()
    .replace(/['’"]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

export function uniqueSlug(source, taken = []) {
  const base = slugify(source) || 'kpi'
  if (!taken.includes(base)) return base
  let index = 2
  let candidate = `${base}-${index}`
  while (taken.includes(candidate)) {
    index += 1
    candidate = `${base}-${index}`
  }
  return candidate
}
