import { describe, expect, it } from 'vitest'
import {
  chooseSide,
  computePlacement,
  inlineEdges,
  inlineStartFor,
  isRectInView,
} from '@/utils/dropdown/placement'

const METRICS = { gap: 4, margin: 8, maxBlock: 320, flipMin: 240 }
const VIEWPORT = { width: 1440, height: 900 }

function rect({ left, top, width = 300, height = 48 }) {
  return { left, top, width, height, right: left + width, bottom: top + height }
}

describe('inlineEdges', () => {
  it('measures from the left edge in LTR', () => {
    expect(inlineEdges(rect({ left: 100, top: 0 }), 1440, 'ltr')).toEqual({ start: 100, end: 400 })
  })

  it('measures from the right edge in RTL so the start is the right side of the field', () => {
    expect(inlineEdges(rect({ left: 100, top: 0 }), 1440, 'rtl')).toEqual({
      start: 1040,
      end: 1340,
    })
  })
})

describe('chooseSide', () => {
  it('opens below when the list fits under the field', () => {
    const result = chooseSide({
      anchor: rect({ left: 0, top: 100 }),
      contentBlockSize: 200,
      viewportHeight: 900,
      metrics: METRICS,
    })
    expect(result).toEqual({ side: 'below', space: 900 - 148 - 4 - 8 })
  })

  it('flips above near the viewport bottom when there is more room above', () => {
    const result = chooseSide({
      anchor: rect({ left: 0, top: 780 }),
      contentBlockSize: 200,
      viewportHeight: 900,
      metrics: METRICS,
    })
    expect(result).toEqual({ side: 'above', space: 780 - 4 - 8 })
  })

  it('needs only the flip threshold below for a tall list before flipping', () => {
    const stays = chooseSide({
      anchor: rect({ left: 0, top: 600 }),
      contentBlockSize: 600,
      viewportHeight: 900,
      metrics: METRICS,
    })
    expect(stays.side).toBe('below')
    const flips = chooseSide({
      anchor: rect({ left: 0, top: 660 }),
      contentBlockSize: 600,
      viewportHeight: 900,
      metrics: METRICS,
    })
    expect(flips.side).toBe('above')
  })

  it('stays below when the space above is not larger', () => {
    const result = chooseSide({
      anchor: rect({ left: 0, top: 30, height: 800 }),
      contentBlockSize: 200,
      viewportHeight: 900,
      metrics: METRICS,
    })
    expect(result.side).toBe('below')
  })
})

describe('inlineStartFor', () => {
  const edges = { start: 1040, end: 1340 }

  it('aligns a select to the field start edge', () => {
    expect(
      inlineStartFor({ edges, inlineSize: 300, viewportWidth: 1440, align: 'start', margin: 8 }),
    ).toBe(1040)
  })

  it('aligns a menu to the trigger end edge so it extends inwards', () => {
    expect(
      inlineStartFor({ edges, inlineSize: 200, viewportWidth: 1440, align: 'end', margin: 8 }),
    ).toBe(1140)
  })

  it('shifts inside the viewport with the margin when it would cross a side', () => {
    expect(
      inlineStartFor({
        edges: { start: 1300, end: 1430 },
        inlineSize: 320,
        viewportWidth: 1440,
        align: 'start',
        margin: 8,
      }),
    ).toBe(1440 - 320 - 8)
    expect(
      inlineStartFor({
        edges: { start: 0, end: 36 },
        inlineSize: 200,
        viewportWidth: 1440,
        align: 'end',
        margin: 8,
      }),
    ).toBe(8)
  })
})

describe('computePlacement', () => {
  it('places a field list under its field, from the right edge in RTL, with a capped height', () => {
    const placement = computePlacement({
      anchor: rect({ left: 200, top: 100 }),
      popup: { inlineSize: 300, blockSize: 500 },
      viewport: VIEWPORT,
      direction: 'rtl',
      align: 'start',
      metrics: METRICS,
    })
    expect(placement).toEqual({
      side: 'below',
      blockOffset: 152,
      maxBlockSize: 320,
      inlineStart: 1440 - 500,
    })
  })

  it('places a field list from the left edge in LTR', () => {
    const placement = computePlacement({
      anchor: rect({ left: 200, top: 100 }),
      popup: { inlineSize: 300, blockSize: 120 },
      viewport: VIEWPORT,
      direction: 'ltr',
      align: 'start',
      metrics: METRICS,
    })
    expect(placement.inlineStart).toBe(200)
    expect(placement.side).toBe('below')
  })

  it('anchors a flipped list to the field top and limits it to the space above', () => {
    const placement = computePlacement({
      anchor: rect({ left: 200, top: 800, width: 36, height: 36 }),
      popup: { inlineSize: 200, blockSize: 400 },
      viewport: VIEWPORT,
      direction: 'ltr',
      align: 'end',
      metrics: METRICS,
    })
    expect(placement).toEqual({
      side: 'above',
      blockOffset: 900 - 800 + 4,
      maxBlockSize: 320,
      inlineStart: 36,
    })
  })

  it('never returns a negative maximum height', () => {
    const placement = computePlacement({
      anchor: rect({ left: 0, top: 0, height: 900 }),
      popup: { inlineSize: 100, blockSize: 100 },
      viewport: VIEWPORT,
      direction: 'ltr',
      align: 'start',
      metrics: METRICS,
    })
    expect(placement.maxBlockSize).toBe(0)
  })
})

describe('isRectInView', () => {
  it('detects a field scrolled out of the viewport', () => {
    expect(isRectInView(rect({ left: 0, top: 100 }), VIEWPORT)).toBe(true)
    expect(isRectInView(rect({ left: 0, top: -60 }), VIEWPORT)).toBe(false)
    expect(isRectInView(rect({ left: 0, top: 900 }), VIEWPORT)).toBe(false)
  })
})
