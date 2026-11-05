export const AA_TEXT = 4.5

export function contrastOf(page, selector) {
  return page
    .locator(selector)
    .first()
    .evaluate((element) => {
      function parse(color) {
        const [r, g, b, a = 1] = color.match(/[\d.]+/g).map(Number)
        return { r, g, b, a }
      }
      function over(top, bottom) {
        const mix = (channel) => top[channel] * top.a + bottom[channel] * (1 - top.a)
        return { r: mix('r'), g: mix('g'), b: mix('b'), a: 1 }
      }
      function luminance(color) {
        const [r, g, b] = [color.r, color.g, color.b].map((value) => {
          const unit = value / 255
          return unit <= 0.03928 ? unit / 12.92 : ((unit + 0.055) / 1.055) ** 2.4
        })
        return 0.2126 * r + 0.7152 * g + 0.0722 * b
      }
      function layersOf(node) {
        const layers = []
        for (let current = node; current; current = current.parentElement) {
          const style = getComputedStyle(current)
          const stops = (style.backgroundImage.match(/rgba?\([^)]+\)/g) ?? []).map(parse)
          const color = parse(style.backgroundColor)
          const layer = stops.length ? stops : [color]
          const visible = layer.filter((stop) => stop.a > 0)
          if (visible.length) layers.push(visible)
          if (visible.length && visible.every((stop) => stop.a === 1)) return layers
        }
        return [...layers, [{ r: 255, g: 255, b: 255, a: 1 }]]
      }
      const candidates = layersOf(element)
        .reverse()
        .reduce(
          (below, layer) => below.flatMap((base) => layer.map((stop) => over(stop, base))),
          [{ r: 255, g: 255, b: 255, a: 1 }],
        )
      const foreground = luminance(parse(getComputedStyle(element).color))
      return Math.min(
        ...candidates.map((candidate) => {
          const background = luminance(candidate)
          const [high, low] =
            foreground > background ? [foreground, background] : [background, foreground]
          return (high + 0.05) / (low + 0.05)
        }),
      )
    })
}
