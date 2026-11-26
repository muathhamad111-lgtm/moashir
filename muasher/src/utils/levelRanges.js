const HUNDREDTHS = 100

function toHundredths(value) {
  return Math.round(Number(value) * HUNDREDTHS)
}

export function levelRanges(levels) {
  const ordered = [...(levels ?? [])]
    .filter(
      (level) =>
        level && level.level && level.minPercent !== null && level.minPercent !== undefined,
    )
    .sort((left, right) => toHundredths(left.minPercent) - toHundredths(right.minPercent))
  return ordered.map((level, index) => {
    const next = ordered[index + 1]
    return {
      level: level.level,
      label: level.label,
      min: toHundredths(level.minPercent) / HUNDREDTHS,
      max: next ? (toHundredths(next.minPercent) - 1) / HUNDREDTHS : null,
    }
  })
}

export function levelOfScore(ranges, score) {
  const value = Number(score)
  if (Number.isNaN(value)) return null
  return ranges.filter((range) => value >= range.min).at(-1) ?? null
}
