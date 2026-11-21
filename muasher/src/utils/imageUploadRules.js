export function imageFileIssue(file, rules) {
  if (!rules.types.includes(file.type)) return 'type'
  if (file.size > rules.maxBytes) return 'size'
  return null
}

export function imageDimensionIssue(dimensions, rules) {
  if (!dimensions) return 'type'
  const sides = [dimensions.width, dimensions.height]
  const fits = sides.every((side) => side >= rules.minSide && side <= rules.maxSide)
  return fits ? null : 'dimensions'
}
