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

function linear(value) {
  const ratio = value / 255
  return ratio <= 0.04045 ? ratio / 12.92 : ((ratio + 0.055) / 1.055) ** 2.4
}

function toOklab(hex) {
  const [red, green, blue] = channels(hex).map(linear)
  const long = Math.cbrt(0.4122214708 * red + 0.5363325363 * green + 0.0514459929 * blue)
  const medium = Math.cbrt(0.2119034982 * red + 0.6806995451 * green + 0.1073969566 * blue)
  const short = Math.cbrt(0.0883024619 * red + 0.2817188376 * green + 0.6299787005 * blue)
  return [
    0.2104542553 * long + 0.793617785 * medium - 0.0040720468 * short,
    1.9779984951 * long - 2.428592205 * medium + 0.4505937099 * short,
    0.0259040371 * long + 0.7827717662 * medium - 0.808675766 * short,
  ]
}

// Perceptual distance between two colours (OKLab, scaled x100: about 2 is just noticeable).
export function perceptualDistance(first, second) {
  const [a, b] = [toOklab(first), toOklab(second)]
  return Math.hypot(a[0] - b[0], a[1] - b[1], a[2] - b[2]) * 100
}
