const NICE_STEPS = [1, 2, 2.5, 5, 10]
const DEFAULT_TICKS = 4

// A clean axis maximum and evenly spaced ticks (0, 250, 500…) covering `max`.
export function niceScale(max, tickCount = DEFAULT_TICKS) {
  const top = Math.max(0, Number(max) || 0)
  if (top === 0)
    return { max: tickCount, ticks: Array.from({ length: tickCount + 1 }, (_, i) => i) }
  const rough = top / tickCount
  const magnitude = 10 ** Math.floor(Math.log10(rough))
  const step = NICE_STEPS.map((factor) => factor * magnitude).find((value) => value >= rough)
  const niceMax = Math.ceil(top / step) * step
  const ticks = []
  for (let tick = 0; tick <= niceMax + step / 2; tick += step) ticks.push(Number(tick.toFixed(6)))
  return { max: niceMax, ticks }
}

// The x position of the centre of band `index` among `count` equal bands across `width`.
export function bandCentre(index, count, width) {
  return count > 0 ? ((index + 0.5) * width) / count : 0
}

export function valueY(value, max, height) {
  return max > 0 ? height - (Math.max(0, Number(value) || 0) / max) * height : height
}

const MARGIN = Object.freeze({ top: 12, right: 12, bottom: 28, left: 56 })
const LABEL_SPACING = 72

// The plot area inside a chart of `width` × `height`, leaving room for the axes.
export function plotFrame(width, height) {
  return {
    ...MARGIN,
    innerWidth: Math.max(0, width - MARGIN.left - MARGIN.right),
    innerHeight: Math.max(0, height - MARGIN.top - MARGIN.bottom),
  }
}

// Every n-th category gets an axis label, so labels never collide.
export function labelEvery(count, innerWidth) {
  const room = Math.max(1, Math.floor(innerWidth / LABEL_SPACING))
  return Math.max(1, Math.ceil(count / room))
}

const BAR_MAX = 24
const BAR_GAP = 2
const BAND_FILL = 0.7
const CORNER = 4

// Bars of one category, side by side: each at most 24px with a 2px surface gap.
export function barLayout(bandWidth, seriesCount) {
  const room = (bandWidth * BAND_FILL - BAR_GAP * (seriesCount - 1)) / Math.max(1, seriesCount)
  const width = Math.max(2, Math.min(BAR_MAX, room))
  return { width, gap: BAR_GAP, group: width * seriesCount + BAR_GAP * (seriesCount - 1) }
}

// A column rising from `baseline` to `top`, square at the baseline, 4px round at its end.
export function columnPath(x, top, width, baseline) {
  const height = baseline - top
  if (height <= 0) return ''
  const radius = Math.min(CORNER, width / 2, height)
  return [
    `M${x},${baseline}`,
    `V${top + radius}`,
    `Q${x},${top} ${x + radius},${top}`,
    `H${x + width - radius}`,
    `Q${x + width},${top} ${x + width},${top + radius}`,
    `V${baseline}`,
    'Z',
  ].join(' ')
}
