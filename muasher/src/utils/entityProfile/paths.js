export function readPath(source, path) {
  return String(path)
    .split('.')
    .reduce(
      (value, key) => (value === null || value === undefined ? undefined : value[key]),
      source,
    )
}

export function writePath(target, path, value) {
  const keys = String(path).split('.')
  const last = keys.pop()
  const parent = keys.reduce((node, key) => {
    node[key] = node[key] ?? {}
    return node[key]
  }, target)
  parent[last] = value
  return target
}
