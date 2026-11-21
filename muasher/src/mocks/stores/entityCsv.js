import { findCity, findRegion } from './regionsStore'
import { referenceOf } from './entitiesStore'

const BYTE_ORDER_MARK = '\uFEFF'
const FORMULA_PREFIX = /^[=+\-@\t\r]/

const COLUMNS = {
  ar: [
    'الرقم المرجعي',
    'الاسم بالعربية',
    'الاسم بالإنجليزية',
    'المنطقة',
    'المدينة',
    'الحالة',
    'البريد الإلكتروني الرسمي',
    'تاريخ التسجيل',
  ],
  en: [
    'Reference',
    'Name in Arabic',
    'Name in English',
    'Region',
    'City',
    'Status',
    'Official e-mail',
    'Registered at',
  ],
}

function cell(value) {
  const text = String(value ?? '')
  const neutral = FORMULA_PREFIX.test(text) ? `'${text}` : text
  return `"${neutral.replaceAll('"', '""')}"`
}

function row(entity, locale) {
  return [
    referenceOf(entity),
    entity.name.ar,
    entity.name.en,
    findRegion(entity.regionId)?.name[locale],
    findCity(entity.cityId)?.name[locale],
    entity.status.replaceAll('-', '_'),
    entity.owner.email,
    entity.createdAt,
  ]
}

export function entitiesCsv(entities, locale) {
  const lines = [COLUMNS[locale], ...entities.map((entity) => row(entity, locale))]
  return BYTE_ORDER_MARK + lines.map((line) => line.map(cell).join(',')).join('\r\n')
}

export function exportFileName(now = new Date()) {
  const stamp = now.toISOString().replace(/[-:]/g, '').replace('T', '-').slice(0, 15)
  return `entities-${stamp}.csv`
}
