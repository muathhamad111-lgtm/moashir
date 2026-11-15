import { ENTITY_LOGO_RULES } from '@/constants/entityProfile'
import { imageDimensionIssue, imageFileIssue } from '@/utils/imageUploadRules'

export function logoFileIssue(file) {
  return imageFileIssue(file, ENTITY_LOGO_RULES)
}

export function logoDimensionIssue(dimensions) {
  return imageDimensionIssue(dimensions, ENTITY_LOGO_RULES)
}
