import {
  boolean as yupBoolean,
  mixed as yupMixed,
  object as yupObject,
  string as yupString,
} from 'yup'
import {
  KPI_COLOR_TOKENS,
  KPI_LIMITS,
  KPI_NUMBER_PATTERN,
  KPI_SLUG_PATTERN,
  KPI_YEAR_RANGE,
} from '@/constants/kpis'
import { iconSchema, localizedSchema } from './cmsSchemas'

function optionalMatch(t, pattern, messageKey) {
  return yupString().test(
    'pattern',
    t(messageKey),
    (value) => !String(value ?? '').trim() || pattern.test(String(value).trim()),
  )
}

function kpiNumber(t) {
  return yupMixed().test('kpi-number', t('kpis.admin.validation.number'), (value) => {
    const text = String(value ?? '').trim()
    return !text || KPI_NUMBER_PATTERN.test(text)
  })
}

function year(t) {
  return yupMixed().test('year', t('kpis.admin.validation.year', KPI_YEAR_RANGE), (value) => {
    if (value === '' || value === null || value === undefined) return true
    const number = Number(value)
    return Number.isInteger(number) && number >= KPI_YEAR_RANGE.min && number <= KPI_YEAR_RANGE.max
  })
}

export function kpiSchema(t) {
  const optional = (max) => localizedSchema(t, max)
  return yupObject({
    name: localizedSchema(t, KPI_LIMITS.name, { required: true }),
    unit: optional(KPI_LIMITS.unit),
    shortDescription: optional(KPI_LIMITS.shortDescription),
    fullDescription: optional(KPI_LIMITS.fullDescription),
    owner: optional(KPI_LIMITS.owner),
    dataSource: optional(KPI_LIMITS.dataSource),
    calcMethod: optional(KPI_LIMITS.calcMethod),
    notes: optional(KPI_LIMITS.notes),
    value: kpiNumber(t),
    target: kpiNumber(t),
    baseline: kpiNumber(t),
    slug: optionalMatch(t, KPI_SLUG_PATTERN, 'kpis.admin.validation.slug').max(KPI_LIMITS.slug),
    measurementYear: year(t),
    externalLink: optionalMatch(t, /^https:\/\/[^\s<>"]+$/, 'kpis.admin.validation.https'),
    icon: iconSchema(t),
    cardColorToken: yupString().oneOf([...KPI_COLOR_TOKENS, '']),
    badgeColorToken: yupString().oneOf([...KPI_COLOR_TOKENS, '']),
    showOnHomepage: yupBoolean(),
  })
}

export function measurementSchema(t, today) {
  return yupObject({
    value: kpiNumber(t).test(
      'required',
      t('validation.required'),
      (value) => String(value ?? '').trim() !== '',
    ),
    measuredOn: yupString()
      .required(t('validation.required'))
      .test('not-future', t('kpis.admin.values.futureDate'), (value) => !value || value <= today),
  })
}
