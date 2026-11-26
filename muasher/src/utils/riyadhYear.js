const RIYADH = 'Asia/Riyadh'

export function riyadhYear(value) {
  if (!value) return ''
  return new Intl.DateTimeFormat('en-u-nu-latn', { year: 'numeric', timeZone: RIYADH }).format(
    new Date(value),
  )
}
