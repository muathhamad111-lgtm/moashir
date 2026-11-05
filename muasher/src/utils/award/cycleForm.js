import { array, boolean, number, object, string } from 'yup'
import {
  AWARD_LIMITS,
  CYCLE_FIELD_GROUPS,
  CYCLE_STAGE_ACCEPTS,
  CYCLE_STATUSES,
} from '@/constants/advanced'
import { endOfRiyadhDay, riyadhDateKey, startOfRiyadhDay } from '@/utils/datetime'

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
const NUMBER_FIELDS = ['year', 'minEntityAgeYears', 'initialReviewSlaDays', 'criteriaVersionId']

function dateKeyOf(value) {
  return value ? riyadhDateKey(value) : ''
}

export function cycleFormValues(cycle, { defaults, published }) {
  if (!cycle) {
    return {
      name: { ar: '', en: '' },
      year: '',
      submissionStart: '',
      submissionEnd: '',
      committeeStart: '',
      committeeEnd: '',
      announcementAt: '',
      regionIds: [],
      criteriaVersionId: published?.versionId ?? null,
      scaleKind: published?.scaleKind ?? '',
      minEntityAgeYears: String(defaults?.minEntityAgeYears ?? ''),
      disqualificationScore: defaults?.disqualificationScore ?? '',
      initialReviewSlaDays: String(defaults?.initialReviewSlaDays ?? ''),
      participationCertificateEnabled: false,
    }
  }
  return {
    name: { ar: cycle.name?.ar ?? '', en: cycle.name?.en ?? '' },
    year: String(cycle.year ?? ''),
    submissionStart: dateKeyOf(cycle.submissionStart),
    submissionEnd: dateKeyOf(cycle.submissionEnd),
    committeeStart: dateKeyOf(cycle.committeeStart),
    committeeEnd: dateKeyOf(cycle.committeeEnd),
    announcementAt: dateKeyOf(cycle.announcementAt),
    regionIds: cycle.regions.map((region) => region.id),
    criteriaVersionId: cycle.criteriaVersionId,
    scaleKind: cycle.scaleKind ?? '',
    minEntityAgeYears: String(cycle.conditions.minEntityAgeYears ?? ''),
    disqualificationScore: cycle.conditions.disqualificationScore ?? '',
    initialReviewSlaDays: String(cycle.initialReviewSlaDays ?? ''),
    participationCertificateEnabled: cycle.participationCertificateEnabled,
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
    submissionStart: string().required(t('validation.required')),
    submissionEnd: dates.submissionEnd,
    committeeStart: dates.committeeStart,
    committeeEnd: dates.committeeEnd,
    announcementAt: dates.announcementAt,
    regionIds: array().min(AWARD_LIMITS.REGIONS_MIN, t('cycles.validation.regions')),
    criteriaVersionId: number().nullable().required(t('cycles.validation.version')),
    scaleKind: string().required(t('validation.required')),
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
  })
}

function wireValue(field, value) {
  if (Object.hasOwn(DATE_BOUNDARY, field)) return value ? DATE_BOUNDARY[field](value) : null
  if (NUMBER_FIELDS.includes(field)) return value === '' || value === null ? null : Number(value)
  if (field === 'name') return { ar: value.ar.trim(), en: value.en.trim() }
  if (field === 'disqualificationScore') return Number(value).toFixed(2)
  return value
}

function payloadFields(values, { includeParticipation }) {
  return Object.keys(values).filter(
    (field) => field !== 'participationCertificateEnabled' || includeParticipation,
  )
}

export function cyclePayload(values, { includeParticipation }) {
  return Object.fromEntries(
    payloadFields(values, { includeParticipation })
      .filter((field) => !(field === 'year' && values.year === ''))
      .map((field) => [field, wireValue(field, values[field])]),
  )
}

function sameValue(left, right) {
  return JSON.stringify(left) === JSON.stringify(right)
}

export function changedCyclePayload(values, initial, { includeParticipation, locked }) {
  return Object.fromEntries(
    payloadFields(values, { includeParticipation })
      .filter(
        (field) =>
          !locked.has(field) &&
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
