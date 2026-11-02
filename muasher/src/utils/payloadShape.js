export function payloadShape(payload) {
  if (payload === null || payload === undefined) return []
  if (Array.isArray(payload)) return ['[array]']
  if (typeof payload !== 'object') return [typeof payload]
  return Object.keys(payload)
}
