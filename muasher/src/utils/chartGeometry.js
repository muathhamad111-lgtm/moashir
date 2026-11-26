const FULL = 100

export function clampPercent(value) {
  const numeric = Number(value)
  if (Number.isNaN(numeric)) return 0
  return Math.max(0, Math.min(FULL, numeric))
}

export function axisPercent(value, isRtl) {
  const clamped = clampPercent(value)
  return isRtl ? FULL - clamped : clamped
}

export function spanStart(from, to, isRtl) {
  return isRtl ? FULL - clampPercent(to) : clampPercent(from)
}

export function timelineX(index, count, isRtl, width = FULL) {
  const ratio = count > 1 ? index / (count - 1) : 0.5
  const position = ratio * width
  return isRtl ? width - position : position
}

export function scaleY(value, { top, height, min = 0, max = FULL }) {
  const ratio = (clampPercent(value) - min) / (max - min)
  return top + height - Math.max(0, Math.min(1, ratio)) * height
}

export function radarPoint(index, count, ratio, { center, radius }) {
  const angle = (Math.PI * 2 * index) / count - Math.PI / 2
  return {
    x: center + Math.cos(angle) * radius * ratio,
    y: center + Math.sin(angle) * radius * ratio,
  }
}

export function pointsAttr(points) {
  return points.map(({ x, y }) => `${x.toFixed(2)},${y.toFixed(2)}`).join(' ')
}

export function deltaOf(current, previous) {
  if (current === null || current === undefined) return null
  if (previous === null || previous === undefined) return null
  return Math.round((Number(current) - Number(previous)) * 10) / 10
}
