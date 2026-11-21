import { POPUP_SIDES } from '@/constants/dropdown'

const RTL = 'rtl'
const END = 'end'

export function inlineEdges(rect, viewportWidth, direction) {
  return direction === RTL
    ? { start: viewportWidth - rect.right, end: viewportWidth - rect.left }
    : { start: rect.left, end: rect.right }
}

export function chooseSide({ anchor, contentBlockSize, viewportHeight, metrics }) {
  const below = viewportHeight - anchor.bottom - metrics.gap - metrics.margin
  const above = anchor.top - metrics.gap - metrics.margin
  const needed = Math.min(contentBlockSize, metrics.flipMin)
  const flips = below < needed && above > below
  return flips
    ? { side: POPUP_SIDES.above, space: above }
    : { side: POPUP_SIDES.below, space: below }
}

export function inlineStartFor({ edges, inlineSize, viewportWidth, align, margin }) {
  const preferred = align === END ? edges.end - inlineSize : edges.start
  const furthest = viewportWidth - inlineSize - margin
  return Math.max(margin, Math.min(preferred, furthest))
}

export function computePlacement({ anchor, popup, viewport, direction, align, metrics }) {
  const { side, space } = chooseSide({
    anchor,
    contentBlockSize: popup.blockSize,
    viewportHeight: viewport.height,
    metrics,
  })
  const blockOffset =
    side === POPUP_SIDES.above
      ? viewport.height - anchor.top + metrics.gap
      : anchor.bottom + metrics.gap
  return {
    side,
    blockOffset,
    maxBlockSize: Math.max(0, Math.min(metrics.maxBlock, space)),
    inlineStart: inlineStartFor({
      edges: inlineEdges(anchor, viewport.width, direction),
      inlineSize: popup.inlineSize,
      viewportWidth: viewport.width,
      align,
      margin: metrics.margin,
    }),
  }
}

export function isRectInView(rect, viewport) {
  return (
    rect.bottom > 0 && rect.top < viewport.height && rect.right > 0 && rect.left < viewport.width
  )
}
