import { ENTITY_LOGO_RULES } from '@/constants/entityProfile'

export function logoFileIssue(file) {
  if (!ENTITY_LOGO_RULES.types.includes(file.type)) return 'type'
  if (file.size > ENTITY_LOGO_RULES.maxBytes) return 'size'
  return null
}

export function logoDimensionIssue(dimensions) {
  if (!dimensions) return 'type'
  const sides = [dimensions.width, dimensions.height]
  const fits = sides.every(
    (side) => side >= ENTITY_LOGO_RULES.minSide && side <= ENTITY_LOGO_RULES.maxSide,
  )
  return fits ? null : 'dimensions'
}
