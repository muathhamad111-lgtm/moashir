import { ENTITY_VISIBILITY } from '@/constants/committee'
import { localizedText } from '@/utils/localizedText'

export function committeeEntityLabel(entity, locale) {
  if (!entity) return ''
  if (entity.visibility === ENTITY_VISIBILITY.CODE_ONLY) return entity.code ?? ''
  return localizedText(entity.name, locale)
}

export function isAnonymous(entity) {
  return entity?.visibility === ENTITY_VISIBILITY.CODE_ONLY
}
