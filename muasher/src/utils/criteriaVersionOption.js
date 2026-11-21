import { statusLabelKey } from '@/utils/canonicalStatus'

export function criteriaVersionOption(t, localized, version) {
  const label = localized(version.label)
  return {
    value: version.id,
    label: t(label ? 'criteria.version.optionWithLabel' : 'criteria.version.option', {
      number: version.versionNumber,
      label,
      status: t(statusLabelKey(version.status)),
    }),
  }
}
