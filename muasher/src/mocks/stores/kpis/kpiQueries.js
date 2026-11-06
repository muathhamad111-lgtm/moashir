import { KPI_DEFAULT_SORT, KPI_PAGE_SIZE, KPI_ADMIN_SORTS, KPI_SEARCH_MIN } from '@/constants/kpis'
import { SITE_SETTINGS_LIMITS } from '@/constants/cms'
import { paginate } from '../../contract/pagination'
import { contentReject, fieldMessage } from '../content/contentRejection'
import { currentSiteSettings } from '../content/siteSettingsWrites'
import { kpiState, liveKpis } from './kpiState'

function normalize(text) {
  return String(text ?? '')
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[ً-ْـ]/g, '')
    .replace(/[أإآ]/g, 'ا')
    .replace(/ة/g, 'ه')
    .replace(/ى/g, 'ي')
}

function matches(localized, term) {
  if (!term) return true
  const needle = normalize(term)
  return [localized?.ar, localized?.en].some((text) => normalize(text).includes(needle))
}

function nameIn(kpi, locale) {
  return (locale === 'en' ? kpi.name.en : kpi.name.ar) || kpi.name.ar || kpi.name.en || ''
}

function numberOrNull(value) {
  return value === null || value === undefined ? null : Number(value)
}

function compareNullsLast(left, right, direction) {
  if (left === null && right === null) return 0
  if (left === null) return 1
  if (right === null) return -1
  return left < right ? -direction : left > right ? direction : 0
}

const SORTERS = {
  order: () => (a, b) => a.order - b.order,
  name: (locale) => (a, b) => nameIn(a, locale).localeCompare(nameIn(b, locale)),
  '-name': (locale) => (a, b) => nameIn(b, locale).localeCompare(nameIn(a, locale)),
  '-created_at': () => (a, b) => b.createdAt.localeCompare(a.createdAt),
  created_at: () => (a, b) => a.createdAt.localeCompare(b.createdAt),
  '-value': () => (a, b) => compareNullsLast(numberOrNull(a.value), numberOrNull(b.value), -1),
  value: () => (a, b) => compareNullsLast(numberOrNull(a.value), numberOrNull(b.value), 1),
  '-last_update': () => (a, b) => compareNullsLast(a.lastUpdate, b.lastUpdate, -1),
  last_update: () => (a, b) => compareNullsLast(a.lastUpdate, b.lastUpdate, 1),
  '-updated_at': () => (a, b) => b.updatedAt.localeCompare(a.updatedAt),
}

function checkSearch(query, keys, allowedSorts) {
  const fields = {}
  keys.forEach((key) => {
    const term = (query.get(key) ?? '').trim()
    if (term && term.length < KPI_SEARCH_MIN)
      fields[key] = [fieldMessage('between', { min: KPI_SEARCH_MIN, max: 100 })]
  })
  const sort = query.get('sort')
  if (sort && !allowedSorts.includes(sort)) fields.sort = [fieldMessage('required')]
  if (Object.keys(fields).length) throw contentReject.validation(fields)
}

const PUBLIC_SORT_VALUES = Object.keys(SORTERS).filter((sort) => sort !== '-updated_at')

function sorted(list, query, locale) {
  const sort = query.get('sort') || KPI_DEFAULT_SORT
  return [...list].sort((a, b) => SORTERS[sort](locale)(a, b) || a.id - b.id)
}

function flag(query, key) {
  const value = query.get(key)
  return value === 'true' ? true : value === 'false' ? false : null
}

function intOf(query, key) {
  const value = query.get(key)
  return value ? Number(value) : null
}

export function publicKpiPage(query, locale) {
  checkSearch(query, ['search', 'filter[owner]'], PUBLIC_SORT_VALUES)
  const filters = {
    categoryId: intOf(query, 'filter[categoryId]'),
    typeId: intOf(query, 'filter[kpiTypeId]'),
    year: intOf(query, 'filter[measurementYear]'),
    status: query.get('filter[status]'),
  }
  const list = liveKpis().filter(
    (kpi) =>
      kpi.published &&
      (!filters.categoryId || kpi.kpiCategoryId === filters.categoryId) &&
      (!filters.typeId || kpi.kpiTypeId === filters.typeId) &&
      (!filters.year || kpi.measurementYear === filters.year) &&
      (!filters.status || kpi.status === filters.status) &&
      matches(kpi.name, query.get('search')) &&
      matches(kpi.owner, query.get('filter[owner]')),
  )
  return paginate(sorted(list, query, locale), query, KPI_PAGE_SIZE.public)
}

export function adminKpiPage(query, locale) {
  checkSearch(
    query,
    ['search'],
    KPI_ADMIN_SORTS.map((entry) => entry.value),
  )
  const published = flag(query, 'filter[published]')
  const featured = flag(query, 'filter[featured]')
  const categoryId = intOf(query, 'filter[categoryId]')
  const list = liveKpis().filter(
    (kpi) =>
      (published === null || kpi.published === published) &&
      (featured === null || kpi.showOnHomepage === featured) &&
      (!categoryId || kpi.kpiCategoryId === categoryId) &&
      matches(kpi.name, query.get('search')),
  )
  return paginate(sorted(list, query, locale), query, KPI_PAGE_SIZE.admin)
}

export function kpiStats() {
  const kpis = kpiState.kpis
  return {
    total: kpis.length,
    published: kpis.filter((kpi) => kpi.published).length,
    unpublished: kpis.filter((kpi) => !kpi.published).length,
    featured: kpis.filter((kpi) => kpi.showOnHomepage).length,
  }
}

export function featuredKpis() {
  const settings = currentSiteSettings()
  if (!settings.showFeaturedKpis) return []
  return liveKpis()
    .filter((kpi) => kpi.published && kpi.showOnHomepage)
    .slice(0, settings.featuredKpiLimit)
}

export const FEATURED_MINIMUM = SITE_SETTINGS_LIMITS.featuredMin
