import { DEFAULT_KPI_COLOR, DEFAULT_KPI_ICON } from '@/constants/kpis'
import { contentImageId } from './contentImage'
import { iconNameFromStored, storedIconFor } from './icons/storedIcon'

const LOCALIZED = [
  'name',
  'unit',
  'shortDescription',
  'fullDescription',
  'owner',
  'dataSource',
  'calcMethod',
  'notes',
]
const NUMBERS = ['value', 'target', 'baseline']

function bilingual(value) {
  return { ar: value?.ar ?? '', en: value?.en ?? '' }
}

function trimmed(value) {
  return { ar: value.ar.trim(), en: value.en.trim() }
}

function textOrNull(value) {
  const text = String(value ?? '').trim()
  return text || null
}

function idOrNull(value) {
  return value === '' || value === null || value === undefined ? null : Number(value)
}

export function kpiFormValues(kpi = null) {
  return {
    ...Object.fromEntries(LOCALIZED.map((field) => [field, bilingual(kpi?.[field])])),
    ...Object.fromEntries(NUMBERS.map((field) => [field, kpi?.[field] ?? ''])),
    slug: kpi?.slug ?? '',
    kpiCategoryId: kpi?.kpiCategoryId ?? '',
    kpiTypeId: kpi?.kpiTypeId ?? '',
    status: kpi?.status ?? '',
    measurementYear: kpi?.measurementYear ?? '',
    externalLink: kpi?.externalLink ?? '',
    icon: iconNameFromStored(kpi?.icon) || DEFAULT_KPI_ICON,
    image: kpi?.image ?? null,
    cardColorToken: kpi?.cardColorToken ?? DEFAULT_KPI_COLOR,
    badgeColorToken: kpi?.badgeColorToken ?? DEFAULT_KPI_COLOR,
    showOnHomepage: Boolean(kpi?.showOnHomepage),
  }
}

function imageField(values, kpi) {
  const nextId = contentImageId(values.image)
  return nextId === contentImageId(kpi?.image) ? {} : { imageFileId: nextId }
}

export function kpiPayload(values, kpi = null) {
  const payload = {
    ...Object.fromEntries(LOCALIZED.map((field) => [field, trimmed(values[field])])),
    ...Object.fromEntries(NUMBERS.map((field) => [field, textOrNull(values[field])])),
    kpiCategoryId: idOrNull(values.kpiCategoryId),
    kpiTypeId: idOrNull(values.kpiTypeId),
    status: values.status || null,
    measurementYear: idOrNull(values.measurementYear),
    externalLink: textOrNull(values.externalLink),
    icon: storedIconFor(values.icon),
    cardColorToken: values.cardColorToken || null,
    badgeColorToken: values.badgeColorToken || null,
    showOnHomepage: Boolean(values.showOnHomepage),
    ...imageField(values, kpi),
  }
  const slug = textOrNull(values.slug)
  if (slug) payload.slug = slug
  return payload
}
