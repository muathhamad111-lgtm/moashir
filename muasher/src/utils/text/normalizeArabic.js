const DIACRITICS = /[ؐ-ًؚ-ٰٟۖ-ۭ]/g
const TATWEEL = /ـ/g
const ALEF_FORMS = /[آأإٱ]/g
const HAMZA_ON_WAW = /ؤ/g
const HAMZA_ON_YA = /ئ/g
const ALEF_MAQSURA = /ى/g
const TA_MARBUTA = /ة/g

export function normalizeArabic(value) {
  return String(value ?? '')
    .normalize('NFC')
    .replace(DIACRITICS, '')
    .replace(TATWEEL, '')
    .replace(ALEF_FORMS, 'ا')
    .replace(HAMZA_ON_WAW, 'و')
    .replace(HAMZA_ON_YA, 'ي')
    .replace(ALEF_MAQSURA, 'ي')
    .replace(TA_MARBUTA, 'ه')
    .toLocaleLowerCase()
    .trim()
}

export function matchesQuery(text, query) {
  const needle = normalizeArabic(query)
  return !needle || normalizeArabic(text).includes(needle)
}

export function startsWithQuery(text, query) {
  const needle = normalizeArabic(query)
  return Boolean(needle) && normalizeArabic(text).startsWith(needle)
}
