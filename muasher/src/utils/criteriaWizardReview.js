import { EVALUATION_TYPES } from '@/constants/criteria'

// The review step of the create wizards: the values about to be added, as label/value rows.

const yesNo = (t, value) => t(`criteria.workspace.sheet.${value ? 'yes' : 'no'}`)

function nameRows(t, values) {
  return [
    { label: t('criteria.fields.nameAr'), value: values.nameAr, dir: 'rtl' },
    { label: t('criteria.fields.nameEn'), value: values.nameEn, dir: 'ltr' },
  ]
}

export function mainReviewRows(values, { t, typeLabel, weight }) {
  return [
    ...nameRows(t, values),
    { label: t('criteria.fields.group'), value: t(`criteria.groups.${values.group}`) },
    { label: t('criteria.fields.weight'), value: weight(values.weight) },
    {
      label: t('criteria.fields.distributeEquallySubs'),
      value: yesNo(t, values.distributeEqually),
    },
    {
      label: t('criteria.workspace.scoring.label.main'),
      value: typeLabel(values.defaultScoringType),
    },
  ]
}

export function subReviewRows(
  values,
  { t, typeLabel, weight, parentDistributes, attachmentCount },
) {
  const direct = values.evaluationType === EVALUATION_TYPES.DIRECT
  return [
    ...nameRows(t, values),
    {
      label: t('criteria.fields.weight'),
      value: parentDistributes ? t('criteria.fields.equalWeightNote') : weight(values.weight),
    },
    {
      label: t('criteria.fields.evaluationType'),
      value: t(`criteria.evaluationType.${direct ? 'direct' : 'guidingPoints'}`),
    },
    {
      label: t(`criteria.workspace.scoring.label.${direct ? 'direct' : 'points'}`),
      value: typeLabel(values.scoringType),
    },
    { label: t('criteria.fields.hasCv'), value: yesNo(t, values.hasCv) },
    { label: t('criteria.attachments.title'), value: String(attachmentCount) },
  ]
}
