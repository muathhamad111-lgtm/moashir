function camelCase(name) {
  return name.replace(/-([a-z0-9])/g, (_, char) => char.toUpperCase())
}

export function iconLabelKey(name) {
  return `icons.names.${camelCase(name)}`
}
