// A smooth line through every point that never overshoots it (monotone cubic,
// Fritsch–Carlson): calmer than straight segments, without inventing peaks or dips.
function slopes(points) {
  const deltas = points.slice(1).map(([x, y], i) => (y - points[i][1]) / (x - points[i][0] || 1))
  return points.map((_, i) => {
    if (i === 0) return deltas[0] ?? 0
    if (i === points.length - 1) return deltas[i - 1] ?? 0
    const [before, after] = [deltas[i - 1], deltas[i]]
    return before * after <= 0 ? 0 : (2 * before * after) / (before + after)
  })
}

export function smoothPath(points) {
  if (!points.length) return ''
  const [first] = points
  if (points.length < 3) return points.map(([x, y], i) => `${i ? 'L' : 'M'}${x},${y}`).join(' ')
  const tangents = slopes(points)
  let path = `M${first[0]},${first[1]}`
  for (let i = 1; i < points.length; i += 1) {
    const [x0, y0] = points[i - 1]
    const [x1, y1] = points[i]
    const third = (x1 - x0) / 3
    path += ` C${x0 + third},${y0 + tangents[i - 1] * third} ${x1 - third},${y1 - tangents[i] * third} ${x1},${y1}`
  }
  return path
}
