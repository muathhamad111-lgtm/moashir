import {
  BRAND_HOVER_SHARE,
  BRAND_SHADE_STEP,
  BRAND_SOFT_SHARE,
  BRAND_SURFACES,
  DERIVED_BRAND_COLORS,
} from '@/constants/branding'
import { darken, mix } from './brandColorMath'
import { contrastRatio, meetsAA } from './contrast'

// Mirrors App\Services\Settings\BrandColorDeriver for the live preview: the auto colours are
// derived readable from the base colours; client overrides are kept exactly as chosen
// (backend/tests/Fixtures/branding/scheme-vectors.json).

const HEX_COLOR = /^#[0-9a-f]{6}$/i

export function isHexColor(value) {
  return typeof value === 'string' && HEX_COLOR.test(value)
}

export const BRAND_SCHEME_KEYS = Object.freeze(['primary', 'secondary', ...DERIVED_BRAND_COLORS])

export function isBrandScheme(scheme) {
  return Boolean(scheme) && BRAND_SCHEME_KEYS.every((key) => isHexColor(scheme[key]))
}

export function normalizeHex(value) {
  const trimmed = typeof value === 'string' ? value.trim().toLowerCase() : ''
  return isHexColor(trimmed) ? trimmed : null
}

function weakestContrast(text, fills) {
  return Math.min(...fills.map((fill) => contrastRatio(text, fill)))
}

// Paper or ink, whichever stays more readable on every given fill (its weakest pair wins).
function readableOn(...fills) {
  const { paper, ink } = BRAND_SURFACES
  return weakestContrast(paper, fills) >= weakestContrast(ink, fills) ? paper : ink
}

// The colour itself, darkened step by step until it reads on the page and on the soft fill.
function textReadableOn(color, soft) {
  const backgrounds = [BRAND_SURFACES.content, soft]
  let shade = color
  for (
    let amount = BRAND_SHADE_STEP;
    !meetsAA(weakestContrast(shade, backgrounds)) && amount <= 1;
    amount += BRAND_SHADE_STEP
  ) {
    shade = darken(color, amount)
  }
  return shade
}

function validOverrides(overrides) {
  return Object.fromEntries(
    DERIVED_BRAND_COLORS.map((key) => [key, normalizeHex(overrides?.[key])]).filter(
      ([, value]) => value,
    ),
  )
}

export function deriveBrandScheme({ primary, secondary }, overrides = {}) {
  const primaryHex = normalizeHex(primary)
  const secondaryHex = normalizeHex(secondary)
  if (!primaryHex || !secondaryHex) return null
  const chosen = validOverrides(overrides)
  const primaryHover = chosen.primaryHover ?? mix(primaryHex, BRAND_SURFACES.ink, BRAND_HOVER_SHARE)
  const primarySoft = chosen.primarySoft ?? mix(primaryHex, BRAND_SURFACES.paper, BRAND_SOFT_SHARE)
  return {
    primary: primaryHex,
    onPrimary: chosen.onPrimary ?? readableOn(primaryHex, primaryHover),
    primaryText: chosen.primaryText ?? textReadableOn(primaryHex, primarySoft),
    primaryHover,
    primarySoft,
    secondary: secondaryHex,
    onSecondary: chosen.onSecondary ?? readableOn(secondaryHex),
  }
}
