import { array, boolean, object, string } from 'yup'
import {
  AWARD_LIMITS,
  CYCLE_FIELD_GROUPS,
  CYCLE_STAGE_ACCEPTS,
  CYCLE_STATUSES,
} from '@/constants/advanced'
import { FINANCE_SETTINGS_RULES } from '@/constants/finance'
import { endOfRiyadhDay, riyadhDateKey, startOfRiyadhDay } from '@/utils/datetime'
import { toWesternDigits } from '@/utils/numerals'
import { priceString } from '@/utils/settings/pathPriceForm'

const SCORE_FORMAT = /^\d{1,3}(\.\d{1,2})?$/
const DATE_ORDER = [
  ['submissionEnd', 'submissionStart', false],
  ['committeeStart', 'submissionEnd', true],
  ['committeeEnd', 'committeeStart', false],
  ['announcementAt', 'committeeEnd', true],
]
const DATE_BOUNDARY = {
  submissionStart: startOfRiyadhDay,
  submissionEnd: endOfRiyadhDay,
  committeeStart: startOfRiyadhDay,
  committeeEnd: endOfRiyadhDay,
  announcementAt: startOfRiyadhDay,
}
const NUMBER_FIELDS = ['year', 'minEntityAgeYears', 'initialReviewSlaDays']

function dateKeyOf(value) {
  return value ? riyadhDateKey(value) : ''
}

const FREE_FEE = '0'

function feeValues(entryFee) {
  const paid = Number(entryFee ?? 0) > 0
  return { entryFeePaid: paid, entryFee: paid ? entryFee : '' }
}

function descriptionValue(description) {
  return { ar: description?.ar ?? '', en: description?.en ?? '' }
}

export function cycleFormValues(cycle, { defaults }) {
  if (!cycle) {
    return {
      name: { ar: '', en: '' },
      year: '',
      description: descriptionValue(null),
      submissionStart: '',
      submissionEnd: '',
      committeeStart: '',
      committeeEnd: '',
      announcementAt: '',
      regionIds: [],
      minEntityAgeYears: String(defaults?.minEntityAgeYears ?? ''),
      disqualificationScore: defaults?.disqualificationScore ?? '',
      initialReviewSlaDays: String(defaults?.initialReviewSlaDays ?? ''),
      participationCertificateEnabled: false,
      ...feeValues(defaults?.entryFee),
    }
  }
  return {
    name: { ar: cycle.name?.ar ?? '', en: cycle.name?.en ?? '' },
    year: String(cycle.year ?? ''),
    description: descriptionValue(cycle.description),
    submissionStart: dateKeyOf(cycle.submissionStart),
    submissionEnd: dateKeyOf(cycle.submissionEnd),
    committeeStart: dateKeyOf(cycle.committeeStart),
    committeeEnd: dateKeyOf(cycle.committeeEnd),
    announcementAt: dateKeyOf(cycle.announcementAt),
    regionIds: cycle.regions.map((region) => region.id),
    minEntityAgeYears: String(cycle.conditions.minEntityAgeYears ?? ''),
    disqualificationScore: cycle.conditions.disqualificationScore ?? '',
    initialReviewSlaDays: String(cycle.initialReviewSlaDays ?? ''),
    participationCertificateEnabled: cycle.participationCertificateEnabled,
    ...feeValues(cycle.entryFee),
  }
}

function integerField(t, min, max) {
  return string()
    .trim()
    .required(t('validation.required'))
    .test(
      'range',
      t('cycles.validation.between', { min, max }),
      (value) => !value || (/^\d+$/.test(value) && Number(value) >= min && Number(value) <= max),
    )
}

function descriptionText(t, other) {
  return string()
    .trim()
    .max(
      AWARD_LIMITS.DESCRIPTION_MAX,
      t('entities.validation.maxChars', { max: AWARD_LIMITS.DESCRIPTION_MAX }),
    )
    .test('pair', t('cycles.validation.descriptionPair'), function check(value) {
      return Boolean(value) || !this.parent[other]?.trim()
    })
}

function orderedDate(t, [field, previous, inclusive], required) {
  const base = required ? string().required(t('validation.required')) : string()
  return base.test('order', t(`cycles.validation.order.${field}`), function check(value) {
    const before = this.parent[previous]
    if (!value || !before) return true
    return inclusive ? value >= before : value > before
  })
}

export function cycleSchema(t) {
  const dates = Object.fromEntries(
    DATE_ORDER.map((rule) => [rule[0], orderedDate(t, rule, rule[0] !== 'announcementAt')]),
  )
  return object({
    name: object({
      ar: string()
        .trim()
        .required(t('validation.required'))
        .max(
          AWARD_LIMITS.NAME_MAX,
          t('entities.validation.maxChars', { max: AWARD_LIMITS.NAME_MAX }),
        ),
      en: string()
        .trim()
        .required(t('validation.required'))
        .max(
          AWARD_LIMITS.NAME_MAX,
          t('entities.validation.maxChars', { max: AWARD_LIMITS.NAME_MAX }),
        ),
    }),
    year: string()
      .trim()
      .test(
        'year',
        t('cycles.validation.between', { min: AWARD_LIMITS.YEAR_MIN, max: AWARD_LIMITS.YEAR_MAX }),
        (value) =>
          !value ||
          (/^\d{4}$/.test(value) &&
            Number(value) >= AWARD_LIMITS.YEAR_MIN &&
            Number(value) <= AWARD_LIMITS.YEAR_MAX),
      ),
    description: object({ ar: descriptionText(t, 'en'), en: descriptionText(t, 'ar') }),
    submissionStart: string().required(t('validation.required')),
    submissionEnd: dates.submissionEnd,
    committeeStart: dates.committeeStart,
    committeeEnd: dates.committeeEnd,
    announcementAt: dates.announcementAt,
    regionIds: array().min(AWARD_LIMITS.REGIONS_MIN, t('cycles.validation.regions')),
    minEntityAgeYears: integerField(t, AWARD_LIMITS.AGE_MIN, AWARD_LIMITS.AGE_MAX),
    disqualificationScore: string()
      .trim()
      .required(t('validation.required'))
      .test(
        'score',
        t('cycles.validation.score'),
        (value) => !value || (SCORE_FORMAT.test(value) && Number(value) <= AWARD_LIMITS.SCORE_MAX),
      ),
    initialReviewSlaDays: integerField(t, AWARD_LIMITS.SLA_MIN, AWARD_LIMITS.SLA_MAX),
    participationCertificateEnabled: boolean(),
    entryFeePaid: boolean(),
    entryFee: string()
      .transform((value) => toWesternDigits(value ?? '').trim())
      .test('fee', t('cycles.validation.entryFee'), function check(value) {
        return !this.parent.entryFeePaid || isPaidFee(value)
      }),
  })
}

function isPaidFee(value) {
  return FINANCE_SETTINGS_RULES.PRICE_INPUT_PATTERN.test(value ?? '') && Number(value) > 0
}

function withFeeChoice(values) {
  if (!Object.hasOwn(values, 'entryFeePaid')) return values
  const { entryFeePaid, ...rest } = values
  return { ...rest, entryFee: entryFeePaid ? values.entryFee : FREE_FEE }
}

function descriptionWire(value) {
  const text = { ar: value.ar.trim(), en: value.en.trim() }
  return text.ar || text.en ? text : null
}

function wireValue(field, value) {
  if (Object.hasOwn(DATE_BOUNDARY, field)) return value ? DATE_BOUNDARY[field](value) : null
  if (NUMBER_FIELDS.includes(field)) return value === '' || value === null ? null : Number(value)
  if (field === 'name') return { ar: value.ar.trim(), en: value.en.trim() }
  if (field === 'description') return descriptionWire(value)
  if (field === 'disqualificationScore') return Number(value).toFixed(2)
  if (field === 'entryFee') return value === '' || value === null ? null : priceString(value)
  return value
}

function payloadFields(values, { includeParticipation }) {
  return Object.keys(values).filter(
    (field) => field !== 'participationCertificateEnabled' || includeParticipation,
  )
}

function omitted(field, values) {
  if (field === 'description') return descriptionWire(values.description) === null
  return ['year', 'entryFee'].includes(field) && (values[field] ?? '') === ''
}

export function cyclePayload(choice, { includeParticipation }) {
  const values = withFeeChoice(choice)
  return Object.fromEntries(
    payloadFields(values, { includeParticipation })
      .filter((field) => !omitted(field, values))
      .map((field) => [field, wireValue(field, values[field])]),
  )
}

function sameValue(left, right) {
  return JSON.stringify(left) === JSON.stringify(right)
}

export function changedCyclePayload(choice, initialChoice, { includeParticipation, locked }) {
  const values = withFeeChoice(choice)
  const initial = withFeeChoice(initialChoice)
  return Object.fromEntries(
    payloadFields(values, { includeParticipation })
      .filter(
        (field) =>
          !locked.has(field) &&
          !(field === 'entryFee' && omitted(field, values)) &&
          !sameValue(wireValue(field, values[field]), wireValue(field, initial[field])),
      )
      .map((field) => [field, wireValue(field, values[field])]),
  )
}

export function lockedCycleFields(status) {
  if (!status) return new Set()
  if (status === CYCLE_STATUSES.CLOSED)
    return new Set([
      'name',
      'year',
      'description',
      ...Object.values(CYCLE_FIELD_GROUPS).flat(),
      'announcementAt',
      'participationCertificateEnabled',
    ])
  return new Set(
    Object.entries(CYCLE_FIELD_GROUPS).flatMap(([group, fields]) =>
      CYCLE_STAGE_ACCEPTS[group].includes(status) ? [] : fields,
    ),
  )
}
