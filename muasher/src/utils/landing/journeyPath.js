const CORNER_MAX = 34
const EDGE_GAP = 38
const EDGE_MIN = 14
const STRAIGHT = 2

export function dotOffset(textRect, frameRect, isRtl) {
  const raw = isRtl
    ? textRect.right - frameRect.left + EDGE_GAP
    : textRect.left - frameRect.left - EDGE_GAP
  return Math.max(EDGE_MIN, Math.min(frameRect.width - EDGE_MIN, raw))
}

function turn({ x, y }, x2, bend) {
  const dx = x2 - x
  const radius = Math.min(CORNER_MAX, Math.abs(dx) / 2)
  const dir = Math.sign(dx)
  const d = [
    `L ${x} ${bend - radius}`,
    `A ${radius} ${radius} 0 0 ${dir > 0 ? 0 : 1} ${x + dir * radius} ${bend}`,
    `L ${x2 - dir * radius} ${bend}`,
    `A ${radius} ${radius} 0 0 ${dir > 0 ? 1 : 0} ${x2} ${bend + radius}`,
  ].join(' ')
  const length = bend - radius - y + Math.PI * radius + Math.abs(dx) - 2 * radius
  return { d, length, end: { x: x2, y: bend + radius } }
}

export function journeyPath(rows) {
  if (!rows.length) return { d: '', length: 1, stops: [] }
  let cursor = { x: rows[0].x, y: rows[0].top + 6 }
  let d = `M ${cursor.x} ${cursor.y}`
  let length = 0
  const stops = []
  rows.forEach((row, index) => {
    stops.push(length + (row.middle - cursor.y))
    const next = rows[index + 1]
    if (!next) {
      const end = row.bottom - 6
      d += ` L ${cursor.x} ${end}`
      length += end - cursor.y
      return
    }
    if (Math.abs(next.x - cursor.x) < STRAIGHT) return
    const bend = (row.bottom + next.top) / 2
    const step = turn(cursor, next.x, bend)
    d += ` ${step.d}`
    length += step.length
    cursor = step.end
  })
  return { d, length: Math.max(1, length), stops }
}
