const FULL_TURN = Math.PI * 2
const QUARTER_TURN = Math.PI / 2

// Rounded to hundredths: enough for any screen, and free of floating-point noise.
function round(value) {
  return Math.round(value * 100) / 100
}

function point(cx, cy, radius, angle) {
  return [round(cx + radius * Math.cos(angle)), round(cy + radius * Math.sin(angle))]
}

// A ring segment between two angles (radians, 0 = 12 o'clock, clockwise).
export function ringSegment({ cx, cy, outer, inner }, from, to) {
  const start = from - QUARTER_TURN
  const end = to - QUARTER_TURN
  const large = to - from > Math.PI ? 1 : 0
  const [x1, y1] = point(cx, cy, outer, start)
  const [x2, y2] = point(cx, cy, outer, end)
  const [x3, y3] = point(cx, cy, inner, end)
  const [x4, y4] = point(cx, cy, inner, start)
  return [
    `M${x1},${y1}`,
    `A${outer},${outer} 0 ${large} 1 ${x2},${y2}`,
    `L${x3},${y3}`,
    `A${inner},${inner} 0 ${large} 0 ${x4},${y4}`,
    'Z',
  ].join(' ')
}

// The share of each value in the whole, as start/end angles; a zero value draws nothing.
export function ringSlices(values) {
  const total = values.reduce((sum, value) => sum + Math.max(0, Number(value) || 0), 0)
  let cursor = 0
  return values.map((value) => {
    const share = total ? Math.max(0, Number(value) || 0) / total : 0
    const slice = { from: cursor * FULL_TURN, to: (cursor + share) * FULL_TURN, share }
    cursor += share
    return slice
  })
}

// A half ring for a gauge, from 9 o'clock (0) to 3 o'clock (1), filled to `ratio`.
export function gaugeArc({ cx, cy, radius }, ratio) {
  const clamped = Math.min(1, Math.max(0, ratio))
  const angle = Math.PI + clamped * Math.PI
  const [x1, y1] = point(cx, cy, radius, Math.PI)
  const [x2, y2] = point(cx, cy, radius, angle)
  return `M${x1},${y1} A${radius},${radius} 0 0 1 ${x2},${y2}`
}
