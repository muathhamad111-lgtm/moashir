import { BRAND_SHADE_STEP, BRAND_SURFACES } from '@/constants/branding'
import { contrastRatio, meetsAA } from './contrast'

const HEX_COLOR = /^#[0-9a-f]{6}$/i

export const CONTRAST_PAIR_KEYS = Object.freeze([
  'primary.text_on_fill',
  'primary.text_on_content',
  'secondary.text_on_fill',
])

export function isHexColor(value) {
  return typeof value === 'string' && HEX_COLOR.test(value)
}

export const BRAND_SCHEME_KEYS = Object.freeze([
  'primary',
  'onPrimary',
  'primaryText',
  'secondary',
  'onSecondary',
])

export function isBrandScheme(scheme) {
  return Boolean(scheme) && BRAND_SCHEME_KEYS.every((key) => isHexColor(scheme[key]))
}

export function normalizeHex(value) {
  const trimmed = typeof value === 'string' ? value.trim().toLowerCase() : ''
  return isHexColor(trimmed) ? trimmed : null
}

export function darken(hex, amount) {
  const keep = 1 - Math.min(1, Math.max(0, amount))
  const channels = [1, 3, 5].map((start) =>
    Math.round(parseInt(hex.slice(start, start + 2), 16) * keep)
      .toString(16)
      .padStart(2, '0'),
  )
  return `#${channels.join('')}`
}

function readableOn(fill) {
  const onPaper = contrastRatio(BRAND_SURFACES.paper, fill)
  const onInk = contrastRatio(BRAND_SURFACES.ink, fill)
  return onPaper >= onInk ? BRAND_SURFACES.paper : BRAND_SURFACES.ink
}

function textOnContent(color) {
  let shade = color
  for (
    let amount = BRAND_SHADE_STEP;
    !meetsAA(contrastRatio(shade, BRAND_SURFACES.content)) && amount <= 1;
    amount += BRAND_SHADE_STEP
  ) {
    shade = darken(color, amount)
  }
  return shade
}

export function deriveBrandScheme({ primary, secondary }) {
  const primaryHex = normalizeHex(primary)
  const secondaryHex = normalizeHex(secondary)
  if (!primaryHex || !secondaryHex) return null
  return {
    primary: primaryHex,
    onPrimary: readableOn(primaryHex),
    primaryText: textOnContent(primaryHex),
    secondary: secondaryHex,
    onSecondary: readableOn(secondaryHex),
  }
}

function pairsOf(scheme) {
  return [
    { key: CONTRAST_PAIR_KEYS[0], foreground: scheme.onPrimary, background: scheme.primary },
    {
      key: CONTRAST_PAIR_KEYS[1],
      foreground: scheme.primaryText,
      background: BRAND_SURFACES.content,
    },
    { key: CONTRAST_PAIR_KEYS[2], foreground: scheme.onSecondary, background: scheme.secondary },
  ]
}

export function brandContrastPairs(scheme) {
  if (!scheme) return []
  return pairsOf(scheme).map((pair) => {
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
