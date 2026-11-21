const CENTRE = 200
const TICK_COUNT = 120
const TICK_STEP = 3
const MAJOR_EVERY = 6
const TICK_OUTER = 190
const TICK_MAJOR = 177
const TICK_MINOR = 184
const ARC_RADIUS = 150
const ARC_GAP = 3

function point(radius, degrees) {
  const radians = (degrees * Math.PI) / 180
  return [CENTRE + radius * Math.sin(radians), CENTRE - radius * Math.cos(radians)]
}

function arcPath(radius, from, to) {
  const [x0, y0] = point(radius, from)
  const [x1, y1] = point(radius, to)
  return `M ${x0} ${y0} A ${radius} ${radius} 0 0 1 ${x1} ${y1}`
}

export function lensTicks() {
  return Array.from({ length: TICK_COUNT }, (_, index) => {
    const major = index % MAJOR_EVERY === 0
    const [x1, y1] = point(TICK_OUTER, index * TICK_STEP)
    const [x2, y2] = point(major ? TICK_MAJOR : TICK_MINOR, index * TICK_STEP)
    return { key: index, major, x1, y1, x2, y2 }
  })
}

export function lensArcs(count) {
  if (!count) return []
  const size = 360 / count
  return Array.from({ length: count }, (_, index) => {
    const [cx, cy] = point(ARC_RADIUS, index * size + size / 2)
    return {
      index,
      d: arcPath(ARC_RADIUS, index * size + ARC_GAP, (index + 1) * size - ARC_GAP),
      cx,
      cy,
    }
  })
}

export function lensRotation(index, count) {
  if (!count) return 0
  const size = 360 / count
  return -(index * size + size / 2)
}
