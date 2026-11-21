import { STORED_ICON_PATTERN } from '@/constants/icons'
import {
  KPI_COLOR_TOKENS,
  KPI_NUMBER_PATTERN,
  KPI_SLUG_PATTERN,
  KPI_STATUS_KEYS,
  KPI_YEAR_RANGE,
} from '@/constants/kpis'
import { contentReject } from '../content/contentRejection'
import {
  createFieldErrors,
  hasLocalizedText,
  normalizeLocalized,
} from '../content/contentValidation'
import { imageOf, isUsableFile } from '../content/publicFiles'
import { kpiState } from './kpiState'

export const KPI_IMAGE_PURPOSE = 'kpi'

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
const ENUMS = {
  status: KPI_STATUS_KEYS,
  cardColorToken: KPI_COLOR_TOKENS,
  badgeColorToken: KPI_COLOR_TOKENS,
}
const SCALARS = ['slug', 'externalLink', 'icon']
const PROHIBITED = ['published', 'order', 'id']
const HTTPS = /^https:\/\/[^\s<>"]+$/

function has(body, field) {
  return Object.prototype.hasOwnProperty.call(body, field)
}

function present(body, field) {
  return has(body, field) && body[field] !== null && body[field] !== ''
}

export function isKpiNumber(value) {
  return KPI_NUMBER_PATTERN.test(String(value ?? '').trim())
}

export function normalizeKpiNumber(value) {
  const text = String(value).trim()
  const negative = text.startsWith('-')
  const [integer, fraction = ''] = text.replace('-', '').split('.')
  const whole = integer.replace(/^0+/, '') || '0'
  const decimals = fraction.replace(/0+$/, '')
  const number = decimals ? `${whole}.${decimals}` : whole
  return negative && number !== '0' ? `-${number}` : number
}

function checkReferences(errors, body) {
  if (present(body, 'kpiCategoryId')) {
    errors.check(
      kpiState.categories.some((entry) => entry.id === Number(body.kpiCategoryId)),
      'kpiCategoryId',
      'required',
    )
  }
  if (present(body, 'kpiTypeId')) {
    errors.check(
      kpiState.types.some((entry) => entry.id === Number(body.kpiTypeId)),
      'kpiTypeId',
      'required',
    )
  }
}

function checkScalars(errors, body) {
  if (present(body, 'slug')) errors.check(KPI_SLUG_PATTERN.test(body.slug), 'slug', 'keyFormat')
  if (present(body, 'externalLink'))
    errors.check(HTTPS.test(body.externalLink), 'externalLink', 'https')
  if (present(body, 'icon')) errors.check(STORED_ICON_PATTERN.test(body.icon), 'icon', 'icon')
  if (present(body, 'measurementYear')) {
    const year = Number(body.measurementYear)
    errors.check(
      year >= KPI_YEAR_RANGE.min && year <= KPI_YEAR_RANGE.max,
      'measurementYear',
      'between',
      KPI_YEAR_RANGE,
    )
  }
}

function validate(body, partial) {
  const errors = createFieldErrors()
  PROHIBITED.filter((field) => has(body, field)).forEach((field) => errors.add(field, 'prohibited'))
  if (!partial || has(body, 'name'))
    errors.check(hasLocalizedText(body.name), 'name.ar', 'localized')
  NUMBERS.filter((field) => present(body, field)).forEach((field) =>
    errors.check(isKpiNumber(body[field]), field, 'kpiNumber'),
  )
  Object.entries(ENUMS)
    .filter(([field]) => present(body, field))
    .forEach(([field, allowed]) => errors.check(allowed.includes(body[field]), field, 'required'))
  checkReferences(errors, body)
  checkScalars(errors, body)
  errors.throwIfAny()
}

function imageChange(body) {
  if (!has(body, 'imageFileId')) return {}
  if (!body.imageFileId) return { image: null }
  if (!isUsableFile(body.imageFileId, KPI_IMAGE_PURPOSE))
    throw contentReject.fileNotUsable('imageFileId')
  return { image: imageOf(body.imageFileId) }
}

export function readKpiChanges(body, { partial }) {
  validate(body, partial)
  const changes = {}
  LOCALIZED.filter((field) => has(body, field)).forEach(
    (field) => (changes[field] = normalizeLocalized(body[field])),
  )
  NUMBERS.filter((field) => has(body, field)).forEach((field) => {
    changes[field] = present(body, field) ? normalizeKpiNumber(body[field]) : null
  })
  Object.keys(ENUMS)
    .filter((field) => has(body, field))
    .forEach((field) => (changes[field] = body[field] || null))
  SCALARS.filter((field) => has(body, field)).forEach(
    (field) => (changes[field] = present(body, field) ? String(body[field]).trim() : null),
  )
  ;['kpiCategoryId', 'kpiTypeId', 'measurementYear']
    .filter((field) => has(body, field))
    .forEach((field) => {
      changes[field] = present(body, field) ? Number(body[field]) : null
    })
  if (has(body, 'showOnHomepage')) changes.showOnHomepage = Boolean(body.showOnHomepage)
  return { ...changes, ...imageChange(body) }
}
