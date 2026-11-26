const NEWTON_STEPS = 8
const PRECISION = 1e-6

function curve(first, second) {
  const c = 3 * first
  const b = 3 * (second - first) - c
  const a = 1 - c - b
  return {
    at: (t) => ((a * t + b) * t + c) * t,
    slope: (t) => (3 * a * t + 2 * b) * t + c,
  }
}

function solveT(x, axis) {
  let t = x
  for (let step = 0; step < NEWTON_STEPS; step += 1) {
    const error = axis.at(t) - x
    const slope = axis.slope(t)
    if (Math.abs(error) < PRECISION || Math.abs(slope) < PRECISION) break
    t -= error / slope
  }
  return Math.min(1, Math.max(0, t))
}

export function cubicBezier([x1, y1, x2, y2]) {
  const xAxis = curve(x1, x2)
  const yAxis = curve(y1, y2)
  return (progress) => {
    if (progress <= 0) return 0
    if (progress >= 1) return 1
    return yAxis.at(solveT(progress, xAxis))
  }
}
