import { localizedText } from '@/utils/localizedText'

export function pathLabelOf(item, t, locale) {
  return localizedText(item?.pathLabel, locale) || t(`assessment.paths.${item?.path}.title`)
}
