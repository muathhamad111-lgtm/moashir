import { allEntities, isDeleted } from './entitiesStore'
import { normalizeSearchText } from './searchIndex'

const STATUSES = ['pending-verification', 'active', 'deactivated']

const SORT_KEYS = {
  name: (entity) => normalizeSearchText(entity.name.ar),
  created_at: (entity) => entity.createdAt,
  founding_year: (entity) => entity.foundingYear,
  governance_grade: (entity) =>
    entity.governanceGrade === null ? -1 : Number(entity.governanceGrade),
}

export const SORTABLE = Object.keys(SORT_KEYS).flatMap((key) => [key, `-${key}`])

export function filtersFrom(query) {
  return {
    search: normalizeSearchText(query.get('search') ?? ''),
    status: query.get('filter[status]'),
    regionId: query.get('filter[regionId]'),
    cityId: query.get('filter[cityId]'),
    entityType: query.get('filter[entityType]'),
    developmentSector: query.get('filter[developmentSector]'),
    orgSize: query.get('filter[orgSize]'),
    trashed: query.get('filter[trashed]') === 'only',
    sort: query.get('sort') || '-created_at',
  }
}

function matchesSearch(entity, needle) {
  if (!needle) return true
  return [entity.name.ar, entity.name.en, entity.owner.email].some((value) =>
    normalizeSearchText(value).includes(needle),
  )
}

function matchesFilters(entity, filters) {
  const checks = [
    [filters.regionId, () => entity.regionId === Number(filters.regionId)],
    [filters.cityId, () => entity.cityId === Number(filters.cityId)],
    [filters.entityType, () => entity.entityType === filters.entityType],
    [filters.developmentSector, () => entity.developmentSector === filters.developmentSector],
    [filters.orgSize, () => entity.orgSize === filters.orgSize],
  ]
  return checks.every(([value, check]) => !value || check())
}

function scoped(filters) {
  return allEntities()
    .filter((entity) => isDeleted(entity) === filters.trashed)
    .filter((entity) => matchesSearch(entity, filters.search))
    .filter((entity) => matchesFilters(entity, filters))
}

function compareBy(sort) {
  const descending = sort.startsWith('-')
  const valueOf = SORT_KEYS[descending ? sort.slice(1) : sort] ?? SORT_KEYS.created_at
  return (left, right) => {
    const a = valueOf(left)
    const b = valueOf(right)
    const order = a < b ? -1 : a > b ? 1 : 0
    return (descending ? -order : order) || left.id - right.id
  }
}

export function queryEntities(filters) {
  return scoped(filters)
    .filter((entity) => !filters.status || entity.status === filters.status)
    .sort(compareBy(filters.sort))
}

export function entityStats(filters) {
  const all = scoped(filters)
  const stats = { total: all.length }
  STATUSES.forEach((status) => {
    stats[status.replaceAll('-', '_')] = all.filter((entity) => entity.status === status).length
  })
  return stats
}
