import { DELTA_DIRECTIONS } from '@/constants/dashboardOverview'

const HUNDRED = 100

function numeric(value) {
  if (value === null || value === undefined || value === '') return null
  const number = Number(value)
  return Number.isNaN(number) ? null : number
}

function directionOf(change) {
  if (change > 0) return DELTA_DIRECTIONS.UP
  if (change < 0) return DELTA_DIRECTIONS.DOWN
  return DELTA_DIRECTIONS.FLAT
}

/**
 * The change from the previous period. A percent card moves in points; any other
 * figure moves by its relative change, which stays unknown when the previous one was zero.
 */
export function deltaOf(current, previous, { inPoints = false } = {}) {
  const now = numeric(current)
  const before = numeric(previous)
  if (now === null || before === null) return null
  const change = now - before
  if (inPoints) return { direction: directionOf(change), points: change, ratio: null }
  if (before === 0) return { direction: directionOf(change), points: null, ratio: null }
  return { direction: directionOf(change), points: null, ratio: (change / before) * HUNDRED }
}
