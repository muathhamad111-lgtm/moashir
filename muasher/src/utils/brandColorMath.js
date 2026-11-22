// Colour arithmetic for the brand scheme. Inputs are normalised six-digit hex codes.

function channels(hex) {
  return [1, 3, 5].map((start) => parseInt(hex.slice(start, start + 2), 16))
}

function toHex(values) {
  return `#${values.map((value) => Math.round(value).toString(16).padStart(2, '0')).join('')}`
}

function clampShare(share) {
  return Math.min(1, Math.max(0, share))
}

export function darken(hex, amount) {
  const keep = 1 - clampShare(amount)
  return toHex(channels(hex).map((value) => value * keep))
}

// Channel-wise mix in gamma-encoded sRGB, as CSS `color-mix(in srgb, …)`: `share` of `first`.
export function mix(first, second, share) {
  const weight = clampShare(share)
  const other = channels(second)
  return toHex(channels(first).map((value, index) => value * weight + other[index] * (1 - weight)))
}
