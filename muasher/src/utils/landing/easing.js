export function clamp(value, min = 0, max = 1) {
  return Math.min(max, Math.max(min, value))
}

export function segment(progress, start, end) {
  return clamp((progress - start) / (end - start))
}

export function easeOut(t) {
  return 1 - Math.pow(1 - t, 3)
}

export function easeInOut(t) {
  return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2
}

export function easeInOutQuad(t) {
  return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2
}
