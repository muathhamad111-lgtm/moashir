import { withFallback } from '../content/contentValidation'
import { kpiState } from './kpiState'

function clone(value) {
  return value === undefined || value === null ? null : JSON.parse(JSON.stringify(value))
}

function referenceOf(list, id) {
  const entry = list.find((candidate) => candidate.id === id)
  return entry ? { id: entry.id, key: entry.key, name: withFallback(entry.name) } : null
}

export function categoryResource(category) {
  return {
    id: category.id,
    key: category.key,
    name: clone(category.name),
    description: clone(category.description),
    icon: category.icon ?? null,
    order: category.order,
    active: category.active,
  }
}

export function typeResource(type) {
  return {
    id: type.id,
    key: type.key,
    name: clone(type.name),
    order: type.order,
    active: type.active,
  }
}

export function publicKpi(kpi) {
  return {
    id: kpi.id,
    slug: kpi.slug,
    name: withFallback(kpi.name),
    shortDescription: withFallback(kpi.shortDescription),
    value: kpi.value,
    target: kpi.target,
    baseline: kpi.baseline,
    unit: withFallback(kpi.unit),
    category: referenceOf(kpiState.categories, kpi.kpiCategoryId),
    type: referenceOf(kpiState.types, kpi.kpiTypeId),
    status: kpi.status,
    owner: withFallback(kpi.owner),
    dataSource: withFallback(kpi.dataSource),
    calcMethod: withFallback(kpi.calcMethod),
    measurementYear: kpi.measurementYear,
    lastUpdate: kpi.lastUpdate,
    externalLink: kpi.externalLink,
    icon: kpi.icon,
    image: clone(kpi.image),
    cardColor: kpi.cardColorToken,
    badgeColor: kpi.badgeColorToken,
    order: kpi.order,
    createdAt: kpi.createdAt,
  }
}

export function historyOf(kpi) {
  return [...kpi.history]
    .sort((a, b) => a.measuredOn.localeCompare(b.measuredOn))
    .map((entry) => ({ ...entry }))
}

export function kpiDetail(kpi) {
  return {
    ...publicKpi(kpi),
    fullDescription: withFallback(kpi.fullDescription),
    notes: withFallback(kpi.notes),
    history: historyOf(kpi),
    seo: { title: withFallback(kpi.name), description: withFallback(kpi.shortDescription) },
  }
}

const EDITOR_FIELDS = [
  'id',
  'slug',
  'name',
  'shortDescription',
  'fullDescription',
  'value',
  'target',
  'baseline',
  'unit',
  'kpiCategoryId',
  'kpiTypeId',
  'status',
  'owner',
  'dataSource',
  'calcMethod',
  'notes',
  'measurementYear',
  'lastUpdate',
  'externalLink',
  'icon',
  'image',
  'cardColorToken',
  'badgeColorToken',
  'showOnHomepage',
  'published',
  'publishedAt',
  'order',
  'createdAt',
  'updatedAt',
]

export function kpiResource(kpi) {
  return {
    ...Object.fromEntries(EDITOR_FIELDS.map((field) => [field, clone(kpi[field])])),
    updatedBy: kpi.updatedById ? { id: kpi.updatedById, name: kpi.updatedByName ?? null } : null,
  }
}

export function kpiOrderEntry(kpi) {
  return {
    id: kpi.id,
    slug: kpi.slug,
    name: clone(kpi.name),
    published: kpi.published,
    order: kpi.order,
  }
}
