import { toRaw } from 'vue'

const ROW_KEYS = new WeakMap()
let lastRowKey = 0

export function rowKeyOf(row) {
  const raw = toRaw(row)
  if (!ROW_KEYS.has(raw)) {
    lastRowKey += 1
    ROW_KEYS.set(raw, `row-${lastRowKey}`)
  }
  return ROW_KEYS.get(raw)
}

export function keepRowKey(source, next) {
  ROW_KEYS.set(toRaw(next), rowKeyOf(source))
  return next
}
