import { CRITERION_GROUPS } from '@/constants/criteria'
import { attachmentsPayload } from '@/utils/criteriaAttachments'
import { normalizeWeight } from '@/utils/weight'

function textOf(content, locale) {
  return content?.[locale] ?? ''
}

export function mainFormValues(criterion) {
  const source = criterion ?? {}
  return {
    nameAr: textOf(source.name, 'ar'),
    nameEn: textOf(source.name, 'en'),
    descriptionAr: textOf(source.description, 'ar'),
    descriptionEn: textOf(source.description, 'en'),
    weight: source.weight ?? '',
    group: source.group ?? CRITERION_GROUPS.ENABLERS,
    distributeEqually: Boolean(source.distributeEqually),
    active: source.active ?? true,
  }
}

export function mainFormPayload(values, attachments, { isEdit }) {
  const hasDescription = values.descriptionAr.trim() || values.descriptionEn.trim()
  const payload = {
    name: { ar: values.nameAr.trim(), en: values.nameEn.trim() },
    description: hasDescription
      ? { ar: values.descriptionAr.trim(), en: values.descriptionEn.trim() }
      : null,
    weight: normalizeWeight(values.weight),
    group: values.group,
    distributeEqually: values.distributeEqually,
    attachments: attachmentsPayload(attachments),
  }
  return isEdit ? payload : { ...payload, active: values.active }
}
