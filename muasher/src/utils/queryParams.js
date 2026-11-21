function isPresent(value) {
  return value !== null && value !== undefined && value !== ''
}

export function filterParams(filters = {}, extra = {}) {
  const entries = Object.entries(filters)
    .filter(([, value]) => isPresent(value))
    .map(([key, value]) => [`filter[${key}]`, value])
  const rest = Object.entries(extra).filter(([, value]) => isPresent(value))
  return Object.fromEntries([...entries, ...rest])
}
