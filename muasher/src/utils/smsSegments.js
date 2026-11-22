// GSM 03.38 basic set plus the extension characters that cost two septets.
const GSM_BASIC =
  '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà'
const GSM_EXTENDED = '^{}\\[~]|€'

const LIMITS = {
  gsm: { single: 160, part: 153 },
  unicode: { single: 70, part: 67 },
}

function isGsm(text) {
  return [...text].every((char) => GSM_BASIC.includes(char) || GSM_EXTENDED.includes(char))
}

function lengthOf(text, encoding) {
  if (encoding === 'unicode') return [...text].length
  return [...text].reduce((total, char) => total + (GSM_EXTENDED.includes(char) ? 2 : 1), 0)
}

/**
 * How an SMS text is billed: Arabic (or any non-GSM character) switches the whole message to
 * UCS-2, which holds 70 characters, or 67 per part once it is split.
 */
export function smsSegments(text) {
  const value = String(text ?? '')
  const encoding = isGsm(value) ? 'gsm' : 'unicode'
  const length = lengthOf(value, encoding)
  const limit = LIMITS[encoding]
  const segments = length <= limit.single ? (length ? 1 : 0) : Math.ceil(length / limit.part)
  return { encoding, length, segments, limit: limit.single }
}
