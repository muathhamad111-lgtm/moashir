import { SCALE_KINDS, SCALE_MAX } from '@/constants/assessment'

export function clampRatio(ratio) {
  if (!Number.isFinite(ratio)) return 0
  if (ratio < 0) return 0
  if (ratio > 1) return 1
  return ratio
}

export function scaleMax(scaleKind) {
  return SCALE_MAX[scaleKind] ?? SCALE_MAX[SCALE_KINDS.OF_10]
}

export function valueToRatio(value, scaleKind) {
  const max = scaleMax(scaleKind)
  if (!max) return 0
  return clampRatio((Number(value) || 0) / max)
}

export function ratioToValue(ratio, scaleKind) {
  return clampRatio(ratio) * scaleMax(scaleKind)
}

export function ratioToPercent(ratio) {
  return Math.round(clampRatio(ratio) * 100)
}

function round2(value) {
  return Math.round(value * 100) / 100
}

function computeSub(sub, ratios) {
  const weight = Number(sub.weight) || 0
  if (sub.evaluationType === 'guiding-points' && Array.isArray(sub.points) && sub.points.length) {
    let earned = 0
    const points = sub.points.map((point) => {
      const ratio = clampRatio(ratios.points?.[point.id] ?? 0)
      const pointWeight = Number(point.weight) || 0
      const pointEarned = ratio * pointWeight
      earned += pointEarned
      return {
        pointId: point.id,
        ratio,
        percent: ratioToPercent(ratio),
        weight: pointWeight,
        earned: round2(pointEarned),
      }
    })
    return {
      subId: sub.id,
      weight,
      earned: round2(earned),
      ratio: weight ? clampRatio(earned / weight) : 0,
      percent: weight ? ratioToPercent(earned / weight) : 0,
      points,
    }
  }
  const ratio = clampRatio(ratios.subs?.[sub.id] ?? 0)
  const earned = ratio * weight
  return {
    subId: sub.id,
    weight,
    earned: round2(earned),
    ratio,
    percent: ratioToPercent(ratio),
    points: [],
  }
}

export function computeResult(tree, ratios = { subs: {}, points: {} }) {
  const safeRatios = { subs: ratios.subs ?? {}, points: ratios.points ?? {} }
  const mainResults = []
  const subResults = []
  const pointResults = []
  let total = 0
  let maxTotal = 0

  for (const main of tree) {
    const mainWeight = Number(main.weight) || 0
    let mainEarned = 0
    const subs = Array.isArray(main.subs) ? main.subs : []
    for (const sub of subs) {
      const subResult = computeSub(sub, safeRatios)
      mainEarned += subResult.earned
      subResult.points.forEach((point) => {
        pointResults.push({ ...point, subCriterionId: sub.id })
      })
      const { points: _points, ...subEntry } = subResult
      subResults.push({ ...subEntry, mainCriterionId: main.id })
    }
    total += mainEarned
    maxTotal += mainWeight
    mainResults.push({
      criterionId: main.id,
      weight: mainWeight,
      earned: round2(mainEarned),
      ratio: mainWeight ? clampRatio(mainEarned / mainWeight) : 0,
      percent: mainWeight ? ratioToPercent(mainEarned / mainWeight) : 0,
    })
  }

  return {
    total: round2(total),
    maxTotal: round2(maxTotal),
    percent: maxTotal ? ratioToPercent(total / maxTotal) : 0,
    main: mainResults,
    sub: subResults,
    points: pointResults,
  }
}
