import { CONTRAST_SURFACES } from '@/constants/branding'
import { contrastRatio, meetsAA } from './contrast'

export function accentContrastPairs(entry) {
  if (!entry) return []
  return [
    { key: 'fill', foreground: CONTRAST_SURFACES.onAccent, background: entry.hex },
    { key: 'dark', foreground: entry.hex, background: CONTRAST_SURFACES.darkContent },
    { key: 'light', foreground: entry.textOnLightHex, background: CONTRAST_SURFACES.lightContent },
  ].map((pair) => {
    const ratio = contrastRatio(pair.foreground, pair.background)
    return { ...pair, ratio, passes: meetsAA(ratio) }
  })
}

export function passesAllContrast(pairs) {
  return pairs.length > 0 && pairs.every((pair) => pair.passes)
}
