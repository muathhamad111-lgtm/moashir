import { stationStateOf } from '@/utils/accredited/submissionProgress'
import { isFilled, scoreFieldName } from './scoreSheet'

function unitsBySub(units) {
  const grouped = new Map()
  units.forEach((unit) => grouped.set(unit.subId, [...(grouped.get(unit.subId) ?? []), unit]))
  return grouped
}

export function scoredStationsOf(mains, units, values) {
  const bySub = unitsBySub(units)
  const filled = (unit) => isFilled(values?.[scoreFieldName(unit)])
  return mains.map((main) => {
    const subUnits = main.subs.map((sub) => bySub.get(sub.id) ?? [])
    const complete = subUnits.filter((list) => list.length > 0 && list.every(filled)).length
    const started = subUnits.some((list) => list.some(filled))
    return {
      id: main.id,
      name: main.name,
      total: main.subs.length,
      complete,
      state: stationStateOf(complete, main.subs.length, started),
    }
  })
}
