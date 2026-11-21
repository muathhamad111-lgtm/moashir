const GSM_BASIC =
  '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà'
const GSM_EXTENDED = '^{}\\[~]|€'

const ENCODINGS = Object.freeze({
  GSM: { name: 'gsm', single: 160, part: 153 },
  UNICODE: { name: 'unicode', single: 70, part: 67 },
})

function isGsm(text) {
  return [...text].every(
    (character) => GSM_BASIC.includes(character) || GSM_EXTENDED.includes(character),
  )
}

function gsmLength(text) {
  return [...text].reduce(
    (total, character) => total + (GSM_EXTENDED.includes(character) ? 2 : 1),
    0,
  )
}

export function smsSegmentEstimate(text = '') {
  const value = String(text ?? '')
  const encoding = isGsm(value) ? ENCODINGS.GSM : ENCODINGS.UNICODE
  const characters = encoding === ENCODINGS.GSM ? gsmLength(value) : [...value].length
  if (characters === 0) return { characters, segments: 0, encoding: encoding.name }
  const segments = characters <= encoding.single ? 1 : Math.ceil(characters / encoding.part)
  return { characters, segments, encoding: encoding.name }
}
