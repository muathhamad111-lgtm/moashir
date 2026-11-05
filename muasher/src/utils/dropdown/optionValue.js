export function valueKey(value) {
  return value === null || value === undefined ? '' : String(value)
}

export function sameValue(left, right) {
  return valueKey(left) === valueKey(right)
}

export function optionIndexOf(options, value) {
  return options.findIndex((option) => sameValue(option.value, value))
}
