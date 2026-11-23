import { BRAND_RAIL, BRAND_SURFACES } from '@/constants/branding'
import { blend, mix } from './brandColorMath'
import { contrastRatio, meetsAA } from './contrast'

// Mirrors App\Services\Settings\BrandRailDeriver for the live preview; the server stays the
// authority (backend/tests/Fixtures/branding/scheme-vectors.json keeps both in step). The derived
// text colours are defaults, not a check: the client may pick any two colours.

function weakestContrast(text, fills) {
  return Math.min(...fills.map((fill) => contrastRatio(text, fill)))
}

function reads(text, fills) {
  return meetsAA(weakestContrast(text, fills))
}

function stopsBetween(top, bottom) {
  return Array.from({ length: BRAND_RAIL.stops + 1 }, (_, index) =>
    blend(top, bottom, index / BRAND_RAIL.stops),
  )
}

function readableOn(stops) {
  const { paper, ink } = BRAND_SURFACES
  return weakestContrast(paper, stops) >= weakestContrast(ink, stops) ? paper : ink
}

function glowFill(primary, bottom, percent) {
  return mix(primary, bottom, percent / 100)
}

// The strongest glow, up to the look's own, under which the text still reads.
function glowPercent(text, stops, primary, bottom) {
  for (
    let percent = BRAND_RAIL.glowMaxPercent;
    percent > 0;
    percent -= BRAND_RAIL.glowStepPercent
  ) {
    if (reads(text, [...stops, glowFill(primary, bottom, percent)])) return percent
  }
  return 0
}

// Inactive items and captions: the text pulled toward the middle stop while it reads on every
// fill, the glow included (menu items can reach the bottom edge).
function mutedText(text, middle, fills) {
  for (
    let percent = BRAND_RAIL.mutedMaxPercent;
    percent > 0;
    percent -= BRAND_RAIL.mutedStepPercent
  ) {
    const candidate = mix(middle, text, percent / 100)
    if (reads(candidate, fills)) return candidate
  }
  return text
}

// The primary as text on the rail, moved toward the text colour only as far as it must.
function accentText(primary, text, fills) {
  for (let percent = 0; percent < 100; percent += BRAND_RAIL.accentStepPercent) {
    const candidate = blend(primary, text, percent / 100)
    if (reads(candidate, fills)) return candidate
  }
  return text
}

export function autoRailColors({ primary, secondary }) {
  return { top: secondary, bottom: blend(secondary, primary, BRAND_RAIL.autoBottomShare) }
}

export function deriveRail(colors, rail = {}) {
  const auto = autoRailColors(colors)
  const top = rail.top || auto.top
  const bottom = rail.bottom || auto.bottom
  const stops = stopsBetween(top, bottom)
  const onRail = readableOn(stops)
  const railGlow = glowPercent(onRail, stops, colors.primary, bottom)
  const fills = [...stops, glowFill(colors.primary, bottom, railGlow)]
  return {
    railTop: top,
    railBottom: bottom,
    railStops: stops,
    onRail,
    onRailMuted: mutedText(onRail, stops[Math.floor(stops.length / 2)], fills),
    railAccent: accentText(colors.primary, onRail, fills),
    railGlow,
  }
}

export function railGradient(stops) {
  const last = stops.length - 1
  return `linear-gradient(180deg, ${stops.map((stop, index) => `${stop} ${(index / last) * 100}%`).join(', ')})`
}

// Ready-made pairs built from the brand colours, offered only when their text reads.
export function railSuggestions({ primary, secondary }) {
  const { paper, ink } = BRAND_SURFACES
  return [
    { id: 'auto', top: null, bottom: null },
    { id: 'deep', top: blend(secondary, ink, 0.35), bottom: blend(secondary, primary, 0.45) },
    { id: 'mono', top: blend(secondary, paper, 0.08), bottom: blend(secondary, ink, 0.45) },
    { id: 'primary', top: blend(primary, ink, 0.72), bottom: blend(primary, ink, 0.5) },
  ].filter((pair) => {
    const rail = deriveRail({ primary, secondary }, pair)
    return reads(rail.onRail, rail.railStops)
  })
}
