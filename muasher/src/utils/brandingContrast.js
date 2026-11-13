import { CONTRAST_SURFACES } from '@/constants/branding'
import { contrastRatio, meetsAA } from './contrast'

export const CONTRAST_PAIR_KEYS = Object.freeze([
  'light.accent_text_on_content',
  'light.text_on_accent',
  'dark.accent_on_content',
  'dark.text_on_accent',
])

function pairsOf(entry) {
  return [
    {
      key: CONTRAST_PAIR_KEYS[0],
      foreground: entry.textOnLightHex,
      background: CONTRAST_SURFACES.lightContent,
    },
    { key: CONTRAST_PAIR_KEYS[1], foreground: CONTRAST_SURFACES.onAccent, background: entry.hex },
    {
      key: CONTRAST_PAIR_KEYS[2],
      foreground: entry.hex,
      background: CONTRAST_SURFACES.darkContent,
    },
    { key: CONTRAST_PAIR_KEYS[3], foreground: CONTRAST_SURFACES.onAccent, background: entry.hex },
  ]
}

export function accentContrastPairs(entry) {
  if (!entry) return []
  return pairsOf(entry).map((pair) => {
    const ratio = contrastRatio(pair.foreground, pair.background)
    return { ...pair, ratio, passes: meetsAA(ratio) }
  })
}

export function passesAllContrast(pairs) {
  return pairs.length > 0 && pairs.every((pair) => pair.passes)
}

export function serverContrastPairs(pairs = []) {
  return pairs.map((pair) => ({
    key: pair.pair,
    foreground: pair.foreground,
    background: pair.background,
    ratio: Number(pair.ratio),
    minimum: Number(pair.minimum),
    passes: false,
  }))
}
