import { CRITERIA_NODE_TYPES as NODE, EVALUATION_TYPES } from '@/constants/criteria'
import { compareWeights } from '@/utils/weight'

const KEY_PATTERN = /^(main|sub|point)-(\d+)$/

export function nodeKey(type, id) {
  return `${type}-${id}`
}

export function parseNodeKey(key) {
  const match = KEY_PATTERN.exec(key ?? '')
  return match ? { type: match[1], id: Number(match[2]) } : null
}

export function scoresPoints(sub) {
  return sub.evaluationType === EVALUATION_TYPES.GUIDING_POINTS
}

function subNeedsAttention(sub) {
  if (!sub.active || !scoresPoints(sub)) return false
  const activePoints = (sub.points ?? []).filter((point) => point.active)
  if (!activePoints.length) return true
  return compareWeights(sub.allocatedPointWeight, sub.weight) !== 0
}

function mainNeedsAttention(main) {
  return main.active && !main.weightBalanced
}

function entry(type, node, parentKey, children = []) {
  return { key: nodeKey(type, node.id), type, node, parentKey, children }
}

function pointEntries(sub, subKey) {
  return (sub.points ?? []).map((point) => entry(NODE.POINT, point, subKey))
}

export function indexTree(tree) {
  const index = new Map()
  for (const main of tree?.mains ?? []) {
    const mainKey = nodeKey(NODE.MAIN, main.id)
    const subKeys = []
    for (const sub of main.subs ?? []) {
      const subEntry = entry(NODE.SUB, sub, mainKey)
      const points = pointEntries(sub, subEntry.key)
      subEntry.children = points.map((point) => point.key)
      subEntry.attention = subNeedsAttention(sub)
      points.forEach((point) => index.set(point.key, point))
      index.set(subEntry.key, subEntry)
      subKeys.push(subEntry.key)
    }
    const mainEntry = entry(NODE.MAIN, main, null, subKeys)
    mainEntry.attention = mainNeedsAttention(main)
    index.set(mainKey, mainEntry)
  }
  return index
}

export function ancestorsOf(index, key) {
  const keys = []
  let current = index.get(key)?.parentKey ?? null
  while (current) {
    keys.unshift(current)
    current = index.get(current)?.parentKey ?? null
  }
  return keys
}

export function nodeLabel(item) {
  return item.type === NODE.POINT ? item.node.description : item.node.name
}
