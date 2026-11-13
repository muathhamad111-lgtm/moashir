import { deltaOf } from './chartGeometry'

function completedTime(item) {
  return new Date(item.completedAt ?? item.createdAt ?? 0).getTime()
}

export function resultSeries(assessments = []) {
  return assessments
    .filter((item) => item.result?.percent !== null && item.result?.percent !== undefined)
    .sort((left, right) => completedTime(left) - completedTime(right))
    .map((item) => ({
      id: item.id,
      number: item.number,
      percent: Number(item.result.percent),
      completedAt: item.completedAt,
    }))
}

function keyOf(main) {
  return String(main.criterionId ?? main.code ?? '')
}

export function criteriaComparison(currentMains = [], previousMains = []) {
  const previous = new Map(previousMains.map((main) => [keyOf(main), main]))
  return currentMains.map((main) => {
    const before = previous.get(keyOf(main)) ?? null
    return {
      key: keyOf(main),
      code: main.code ?? '',
      name: main.name ?? null,
      percent: Number(main.percent ?? 0),
      previous: before ? Number(before.percent ?? 0) : null,
      delta: before ? deltaOf(main.percent, before.percent) : null,
    }
  })
}

export function rankedCriteria(criteria = []) {
  return [...criteria].sort((left, right) => right.percent - left.percent)
}

export function seriesDelta(series = []) {
  if (series.length < 2) return null
  return deltaOf(series.at(-1).percent, series.at(-2).percent)
}
