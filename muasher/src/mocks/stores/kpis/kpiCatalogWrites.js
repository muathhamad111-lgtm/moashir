import { STORED_ICON_PATTERN } from '@/constants/icons'
import { KPI_CATALOG_KEY_PATTERN, KPI_LIMITS } from '@/constants/kpis'
import { contentReject } from '../content/contentRejection'
import {
  createFieldErrors,
  hasLocalizedText,
  normalizeLocalized,
} from '../content/contentValidation'
import { catalogOf, kpiState, nextKpiId } from './kpiState'
import { categoryResource, typeResource } from './kpiSerializers'

const KEY_LENGTH = { categories: KPI_LIMITS.categoryKey, types: KPI_LIMITS.typeKey }
const SEQUENCE = { categories: 'category', types: 'type' }
const IN_USE_CODE = { categories: 'category', types: 'type' }
const REFERENCE = { categories: 'kpiCategoryId', types: 'kpiTypeId' }

function catalogResource(kind, entry) {
  return kind === 'categories' ? categoryResource(entry) : typeResource(entry)
}

function requireEntry(kind, entryId) {
  const entry = catalogOf(kind).find((candidate) => candidate.id === Number(entryId))
  if (!entry) throw contentReject.notFound()
  return entry
}

function validate(kind, body, current) {
  const errors = createFieldErrors()
  if (!current || body.key !== undefined) {
    const key = String(body.key ?? '')
    errors.check(
      KPI_CATALOG_KEY_PATTERN.test(key) && key.length <= KEY_LENGTH[kind],
      'key',
      'keyFormat',
    )
    errors.check(
      !catalogOf(kind).some((entry) => entry.key === key && entry.id !== current?.id),
      'key',
      'keyFormat',
    )
  }
  if (!current || body.name !== undefined)
    errors.check(hasLocalizedText(body.name), 'name.ar', 'localized')
  if (kind === 'types')
    ['description', 'icon']
      .filter((field) => body[field] !== undefined)
      .forEach((field) => errors.add(field, 'prohibited'))
  if (kind === 'categories' && body.icon)
    errors.check(STORED_ICON_PATTERN.test(body.icon), 'icon', 'icon')
  errors.throwIfAny()
}

function changesOf(kind, body) {
  const changes = {}
  if (body.key !== undefined) changes.key = String(body.key).trim()
  if (body.name !== undefined) changes.name = normalizeLocalized(body.name)
  if (kind === 'categories' && body.description !== undefined)
    changes.description = normalizeLocalized(body.description)
  if (kind === 'categories' && body.icon !== undefined) changes.icon = body.icon || null
  if (body.order !== undefined) changes.order = Number(body.order)
  if (body.active !== undefined) changes.active = Boolean(body.active)
  return changes
}

export function createCatalogEntry(kind, body) {
  validate(kind, body, null)
  const list = catalogOf(kind)
  const base = kind === 'categories' ? { description: null, icon: null } : {}
  const entry = {
    id: nextKpiId(SEQUENCE[kind]),
    ...base,
    order: list.length + 1,
    active: true,
    ...changesOf(kind, body),
  }
  list.push(entry)
  return catalogResource(kind, entry)
}

export function updateCatalogEntry(kind, entryId, body) {
  const entry = requireEntry(kind, entryId)
  validate(kind, body, entry)
  Object.assign(entry, changesOf(kind, body))
  return catalogResource(kind, entry)
}

export function removeCatalogEntry(kind, entryId) {
  const entry = requireEntry(kind, entryId)
  const users = [...kpiState.kpis, ...(kind === 'types' ? kpiState.archived : [])]
  const kpiCount = users.filter((kpi) => kpi[REFERENCE[kind]] === entry.id).length
  if (kpiCount) throw contentReject.catalogInUse(IN_USE_CODE[kind], kpiCount)
  const list = catalogOf(kind)
  list.splice(list.indexOf(entry), 1)
}

export function listedCatalog(kind, { activeOnly = false } = {}) {
  return [...catalogOf(kind)]
    .filter((entry) => !activeOnly || entry.active)
    .sort((a, b) => a.order - b.order || a.id - b.id)
    .map((entry) => catalogResource(kind, entry))
}
