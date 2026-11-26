import { object as yupObject, boolean as yupBoolean, string as yupString } from 'yup'
import {
  CRITERIA_DESCRIPTION_MAX_LENGTH,
  CRITERION_GROUP_ORDER,
  CRITERION_GROUPS,
  SCORING_TYPES,
} from '@/constants/criteria'
import { pairedText, requiredText, weightText } from '@/utils/criteriaSchemas'
import { attachmentsPayload } from '@/utils/criteriaAttachments'
import { normalizeWeight } from '@/utils/weight'

// Server field names → form field names, so a server error lands on its field.
export const MAIN_FIELD_ALIASES = Object.freeze({
  'name.ar': 'nameAr',
  'name.en': 'nameEn',
  name: 'nameAr',
  'description.ar': 'descriptionAr',
  'description.en': 'descriptionEn',
  description: 'descriptionAr',
})

export function mainGroupOptions(t) {
  return CRITERION_GROUP_ORDER.map((group) => ({
    value: group,
    label: t(`criteria.groups.${group}`),
  }))
}

export function mainFormSchema(t) {
  return yupObject({
    nameAr: requiredText(t),
    nameEn: requiredText(t),
    descriptionAr: pairedText(t, 'descriptionEn', CRITERIA_DESCRIPTION_MAX_LENGTH),
    descriptionEn: pairedText(t, 'descriptionAr', CRITERIA_DESCRIPTION_MAX_LENGTH),
    weight: weightText(t),
    group: yupString().required(t('validation.required')),
    defaultScoringType: yupString(),
    distributeEqually: yupBoolean(),
    active: yupBoolean(),
  })
}

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
    defaultScoringType: source.defaultScoringType ?? SCORING_TYPES.SCORE,
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
  // The default type of an existing main criterion is changed from its Evaluation tab, not here.
  return isEdit
    ? payload
    : { ...payload, defaultScoringType: values.defaultScoringType, active: values.active }
}
