import { EVALUATION_TYPES } from '@/constants/criteria'
import { SCALE_KINDS, SCORE_LEVELS } from '@/constants/assessment'
import { toHundredths } from '@/utils/weight'

export const PERCENT_HUNDRED = 1000000
const EARNED_DISPLAY_DIVISOR = 1000000
const PERCENT_DISPLAY_DIVISOR = 100
const RAW_DECIMALS = 2
const RAW_PATTERN = /^(\d{1,10})(?:\.(\d+))?$/

const SCALE_MAXIMUM_HUNDREDTHS = {
  [SCALE_KINDS.OF_10]: 1000,
  [SCALE_KINDS.RELATIVE]: 10000,
}

export function scaleMaximumHundredths(scaleKind) {
  return SCALE_MAXIMUM_HUNDREDTHS[scaleKind]
}

export function divideHalfUp(numerator, denominator) {
  return Math.floor((2 * numerator + denominator) / (2 * denominator))
}

function multiplyDivideHalfUp(value, multiplier, divisor) {
  const quotient = Math.floor(value / divisor)
  const remainder = value % divisor
  return quotient * multiplier + divideHalfUp(remainder * multiplier, divisor)
}

export function formatFixed(value, decimals) {
  const scale = 10 ** decimals
  return `${Math.floor(value / scale)}.${String(value % scale).padStart(decimals, '0')}`
}

export function parseRawHundredths(text) {
  const parts = RAW_PATTERN.exec(String(text).trim())
  if (!parts) return null
  const fraction = parts[2] ?? ''
  if (fraction.length > RAW_DECIMALS && /[^0]/.test(fraction.slice(RAW_DECIMALS))) return null
  return Number(parts[1]) * 100 + Number(fraction.slice(0, RAW_DECIMALS).padEnd(RAW_DECIMALS, '0'))
}

export function rawPercent(hundredths, scaleKind) {
  return Math.floor((hundredths * PERCENT_HUNDRED) / scaleMaximumHundredths(scaleKind))
}

export const displayPercent = (tenThousandths) =>
  formatFixed(divideHalfUp(tenThousandths, PERCENT_DISPLAY_DIVISOR), 2)
export const displayEarned = (hundredMillionths) =>
  formatFixed(divideHalfUp(hundredMillionths, EARNED_DISPLAY_DIVISOR), 2)
export const decimalRaw = (hundredths) => formatFixed(hundredths, RAW_DECIMALS)
export const decimalWeight = (hundredths) => formatFixed(Math.max(0, hundredths), 2)

function percentOf(hundredMillionths, weightHundredths) {
  if (weightHundredths <= 0) return 0
  return Math.min(PERCENT_HUNDRED, divideHalfUp(hundredMillionths, weightHundredths))
}

export function unitKey(level, id) {
  return `${level}:${id}`
}

function scoresPoints(sub) {
  return sub.evaluationType === EVALUATION_TYPES.GUIDING_POINTS
}

export function scorableUnits(tree) {
  return tree.mains.flatMap((main) =>
    main.subs.flatMap((sub) =>
      scoresPoints(sub)
        ? sub.points.map((point) => ({ level: SCORE_LEVELS.POINT, id: point.id, code: point.code }))
        : [{ level: SCORE_LEVELS.SUB, id: sub.id, code: sub.code }],
    ),
  )
}

function scoredUnit(level, node, sheet, scaleKind) {
  const raw = sheet.get(unitKey(level, node.id)) ?? null
  const percent = raw === null ? 0 : rawPercent(raw, scaleKind)
  const weight = toHundredths(node.weight)
  return {
    id: node.id,
    code: node.code,
    weight,
    raw,
    percent,
    earned: percent * Math.max(0, weight),
  }
}

function normalizedEarned(subWeight, points) {
  const weighted = points.reduce(
    (total, point) => total + point.percent * Math.max(0, point.weight),
    0,
  )
  const pointWeights = points.reduce((total, point) => total + Math.max(0, point.weight), 0)
  if (pointWeights === 0 || subWeight <= 0) return 0
  return multiplyDivideHalfUp(weighted, subWeight, pointWeights)
}

function subResult(sub, sheet, scaleKind) {
  if (!scoresPoints(sub)) {
    return {
      ...scoredUnit(SCORE_LEVELS.SUB, sub, sheet, scaleKind),
      evaluationType: sub.evaluationType,
      points: [],
    }
  }
  const points = sub.points.map((point) => scoredUnit(SCORE_LEVELS.POINT, point, sheet, scaleKind))
  const weight = toHundredths(sub.weight)
  const earned = normalizedEarned(weight, points)
  return {
    id: sub.id,
    code: sub.code,
    weight,
    evaluationType: sub.evaluationType,
    raw: null,
    percent: percentOf(earned, weight),
    earned,
    points,
  }
}

function mainResult(main, sheet, scaleKind) {
  const subs = main.subs.map((sub) => subResult(sub, sheet, scaleKind))
  const weight = toHundredths(main.weight)
  const earned = subs.reduce((total, sub) => total + sub.earned, 0)
  return { id: main.id, code: main.code, weight, percent: percentOf(earned, weight), earned, subs }
}

export function computeSelfResult(tree, sheet, scaleKind) {
  const mains = tree.mains.map((main) => mainResult(main, sheet, scaleKind))
  const total = mains.reduce((sum, main) => sum + main.earned, 0)
  const totalWeight = toHundredths(tree.totalWeight)
  return { total, totalWeight, percent: percentOf(total, totalWeight), mains }
}
