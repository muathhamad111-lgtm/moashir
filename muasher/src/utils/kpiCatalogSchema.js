import {
  boolean as yupBoolean,
  number as yupNumber,
  object as yupObject,
  string as yupString,
} from 'yup'
import { KPI_CATALOG_KEY_PATTERN, KPI_LIMITS } from '@/constants/kpis'
import { iconSchema, localizedSchema } from './cmsSchemas'
import { iconNameFromStored, storedIconFor } from './icons/storedIcon'

const ORDER_MAX = 32000

export function kpiCatalogSchema(t, kind) {
  const keyMax = kind === 'categories' ? KPI_LIMITS.categoryKey : KPI_LIMITS.typeKey
  return yupObject({
    key: yupString()
      .trim()
      .required(t('validation.required'))
      .max(keyMax, t('cms.validation.maxChars', { max: keyMax }))
      .matches(KPI_CATALOG_KEY_PATTERN, t('kpis.admin.validation.catalogKey')),
    name: localizedSchema(t, KPI_LIMITS.catalogName, { required: true }),
    description: localizedSchema(t, KPI_LIMITS.catalogDescription),
    icon: iconSchema(t),
    order: yupNumber()
      .transform((value, original) => (original === '' ? undefined : value))
      .typeError(t('validation.number'))
      .min(0, t('validation.min', { min: 0 }))
      .max(ORDER_MAX),
    active: yupBoolean(),
  })
}

export function catalogFormValues(entry) {
  return {
    key: entry?.key ?? '',
    name: { ar: entry?.name?.ar ?? '', en: entry?.name?.en ?? '' },
    description: { ar: entry?.description?.ar ?? '', en: entry?.description?.en ?? '' },
    icon: iconNameFromStored(entry?.icon),
    order: entry?.order ?? '',
    active: entry?.active ?? true,
  }
}

export function catalogPayload(kind, values) {
  const payload = {
    key: values.key.trim(),
    name: { ar: values.name.ar.trim(), en: values.name.en.trim() },
    active: Boolean(values.active),
  }
  if (values.order !== '' && values.order !== undefined) payload.order = Number(values.order)
  if (kind === 'categories') {
    payload.description = { ar: values.description.ar.trim(), en: values.description.en.trim() }
    payload.icon = storedIconFor(values.icon)
  }
  return payload
}
