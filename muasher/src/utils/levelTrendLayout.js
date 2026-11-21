import { scaleY, timelineX } from '@/utils/chartGeometry'

const FULL = 100
const STEP = 10
const HEADROOM = 5
const LABEL_EDGE = 4
const VALUE_GAP = 12
const VALUE_BELOW_GAP = 22

export const LEVEL_TREND_SIZE = Object.freeze({
  height: 248,
  labelColumn: 112,
  endGutter: 16,
  top: 28,
  bottom: 40,
  inset: 48,
})

export function trendScaleMin(percents, firstThreshold) {
  const lowest = Math.min(...percents.map(Number), firstThreshold ?? FULL)
  const floored = Math.floor((lowest - HEADROOM) / STEP) * STEP
  return Math.max(0, Math.min(floored, FULL - STEP))
}

function plotOf(width, isRtl) {
  const { labelColumn, endGutter, top, bottom, height } = LEVEL_TREND_SIZE
  const plotWidth = Math.max(0, width - labelColumn - endGutter)
  return {
    x: isRtl ? endGutter : labelColumn,
    width: plotWidth,
    top,
    height: height - top - bottom,
  }
}

function bandsOf(ranges, scale) {
  return ranges.map((range, index) => {
    const upper = ranges[index + 1]?.min ?? FULL
    const top = scaleY(upper, scale)
    const bottom = scaleY(range.min, scale)
    return {
      level: range.level,
      min: range.min,
      y: top,
      height: bottom - top,
      labelY: (top + bottom) / 2,
    }
  })
}

function pointsOf(series, plot, scale, isRtl) {
  const span = Math.max(0, plot.width - LEVEL_TREND_SIZE.inset * 2)
  return series.map((entry, index) => {
    const y = scaleY(entry.percent, scale)
    const nearTop = y - VALUE_GAP < plot.top
    return {
      key: entry.id,
      percent: entry.percent,
      completedAt: entry.completedAt,
      x: plot.x + LEVEL_TREND_SIZE.inset + timelineX(index, series.length, isRtl, span),
      y,
      valueY: nearTop ? y + VALUE_BELOW_GAP : y - VALUE_GAP,
      isLatest: index === series.length - 1,
    }
  })
}

export function levelTrendLayout({ series, ranges, width, isRtl }) {
  const plot = plotOf(width, isRtl)
  const min = trendScaleMin(
    series.map((entry) => entry.percent),
    ranges[0]?.min,
  )
  const scale = { top: plot.top, height: plot.height, min, max: FULL }
  return {
    width,
    height: LEVEL_TREND_SIZE.height,
    plot,
    min,
    floorY: scaleY(min, scale),
    labelX: isRtl ? width - LABEL_EDGE : LABEL_EDGE,
    dateY: LEVEL_TREND_SIZE.height - LABEL_EDGE * 3,
    bands: bandsOf(ranges, scale),
    points: pointsOf(series, plot, scale, isRtl),
  }
}
