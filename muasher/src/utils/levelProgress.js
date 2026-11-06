import { levelOfScore } from './levelRanges'

export function nextLevelOf(ranges, percent) {
  const value = Number(percent)
  if (percent === null || percent === undefined || Number.isNaN(value)) return null
  return ranges.find((range) => range.min > value) ?? null
}

export function remainingTo(range, percent) {
  if (!range) return null
  return Math.round((range.min - Number(percent)) * 100) / 100
}

export function isTopLevel(ranges, percent) {
  const current = levelOfScore(ranges, percent)
  return Boolean(current) && current.level === ranges.at(-1)?.level
}
