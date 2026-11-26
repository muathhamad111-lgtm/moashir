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

function gamma(value) {
  return value <= 0.0031308 ? 12.92 * value : 1.055 * value ** (1 / 2.4) - 0.055
}

// `** (1 / 3)`, not Math.cbrt: PHP has no cube root, and the blend must match it to the bit.
function toOklab(hex) {
  const [red, green, blue] = channels(hex).map(linear)
  const long = (0.4122214708 * red + 0.5363325363 * green + 0.0514459929 * blue) ** (1 / 3)
  const medium = (0.2119034982 * red + 0.6806995451 * green + 0.1073969566 * blue) ** (1 / 3)
  const short = (0.0883024619 * red + 0.2817188376 * green + 0.6299787005 * blue) ** (1 / 3)
  return [
    0.2104542553 * long + 0.793617785 * medium - 0.0040720468 * short,
    1.9779984951 * long - 2.428592205 * medium + 0.4505937099 * short,
    0.0259040371 * long + 0.7827717662 * medium - 0.808675766 * short,
  ]
}

function fromOklab([lightness, greenRed, blueYellow]) {
  const long = (lightness + 0.3963377774 * greenRed + 0.2158037573 * blueYellow) ** 3
  const medium = (lightness - 0.1055613458 * greenRed - 0.0638541728 * blueYellow) ** 3
  const short = (lightness - 0.0894841775 * greenRed - 1.291485548 * blueYellow) ** 3
  return toHex(
    [
      4.0767416621 * long - 3.3077115913 * medium + 0.2309699292 * short,
      -1.2684380046 * long + 2.6097574011 * medium - 0.3413193965 * short,
      -0.0041960863 * long - 0.7034186147 * medium + 1.707614701 * short,
    ].map((value) => clampShare(gamma(value)) * 255),
  )
}

// Blend in OKLab, `share` of the way from `from` to `to` (App\Support\Branding\ColorShade::blend):
// the middle keeps the lightness and colour of its ends instead of going grey.
export function blend(from, to, share) {
  const weight = clampShare(share)
  if (weight === 0) return from
  if (weight === 1) return to
  const [start, end] = [toOklab(from), toOklab(to)]
  return fromOklab(start.map((value, index) => value + (end[index] - value) * weight))
}
