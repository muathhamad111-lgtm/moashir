import { compareWeights, normalizeWeight } from '@/utils/weight'

export function movedIds(items, index, delta) {
  const target = index + delta
  if (target < 0 || target >= items.length) return null
  const ids = items.map((item) => item.id)
  ;[ids[index], ids[target]] = [ids[target], ids[index]]
  return ids
}

export function weightChanges(edited, originals) {
  const before = new Map(originals.map((item) => [item.id, item.weight]))
  return edited
    .filter((item) => before.has(item.id) && compareWeights(item.weight, before.get(item.id)) !== 0)
    .map((item) => ({
      id: item.id,
      weight: normalizeWeight(item.weight),
      delta: compareWeights(item.weight, before.get(item.id)),
    }))
    .sort((left, right) => left.delta - right.delta)
    .map(({ id, weight }) => ({ id, weight }))
}
