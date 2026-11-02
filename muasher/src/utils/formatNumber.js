export function formatNumber(n, value, format = 'default') {
  if (value === null || value === undefined) return ''
  const numeric = Number(value)
  if (Number.isNaN(numeric)) return String(value)
  const formatted = n(numeric, format)
  return formatted === '' ? String(numeric) : formatted
}
