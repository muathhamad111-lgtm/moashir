import { clampPercent } from './chartGeometry'
import { LEVEL_TRACK_LAYOUT } from '@/constants/levelHeroMotion'

const FULL = 100

export function levelAxis(ranges) {
  const first = ranges[0]?.min ?? null
  if (!first || first >= FULL) return (value) => clampPercent(value)
  const levelShare = FULL - LEVEL_TRACK_LAYOUT.stubShare
  return (value) => {
    const clamped = clampPercent(value)
    if (clamped <= first) return (clamped / first) * LEVEL_TRACK_LAYOUT.stubShare
    return LEVEL_TRACK_LAYOUT.stubShare + ((clamped - first) / (FULL - first)) * levelShare
  }
}

export function trackSegments(ranges) {
  if (!ranges.length) return []
  const stub = { key: 'below', tier: 'neutral', from: 0, to: ranges[0].min, threshold: null }
  const levels = ranges.map((range, index) => ({
    key: range.level,
    tier: range.level,
    from: range.min,
    to: ranges[index + 1]?.min ?? FULL,
    threshold: range.min,
  }))
  return [stub, ...levels]
}

function hasScore(percent) {
  return percent !== null && percent !== undefined && !Number.isNaN(Number(percent))
}

export function splitSegment(segment, percent, isLast) {
  if (!hasScore(percent))
    return { reached: null, ahead: [segment.from, segment.to], remaining: null }
  const score = Number(percent)
  if (score >= segment.to)
    return { reached: [segment.from, segment.to], ahead: null, remaining: null }
  if (score <= segment.from)
    return { reached: null, ahead: [segment.from, segment.to], remaining: null }
  if (isLast) return { reached: [segment.from, score], ahead: [score, segment.to], remaining: null }
  return { reached: [segment.from, score], ahead: null, remaining: [score, segment.to] }
}

export function currentSegmentKey(segments, percent) {
  if (!hasScore(percent)) return null
  const score = Number(percent)
  return segments.filter((segment) => score >= segment.from).at(-1)?.key ?? null
}
