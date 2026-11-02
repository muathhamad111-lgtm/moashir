import { paginate } from '../../contract/pagination'
import { fieldMessage } from '../assessments/assessmentRejection'
import { entityOf, isFrozen } from '../assessments/assessmentsStore'
import { activeAssignment, isOverdue, reviewedRecords } from './accreditedRecords'
import { REVIEWED_PATHS, pathRules } from '../assessments/reviewedPaths'
import { isPositiveInteger, throwIfErrors } from './accreditedValidation'

const FILTER_KEYS = ['filter[path]', 'filter[status]', 'filter[overdue]', 'filter[assessorId]', 'filter[cycleId]']
const ADVANCED = 'advanced'
const SORTABLE = ['submittedAt', 'dueAt', 'status', 'createdAt']
const OVERDUE_VALUES = { true: true, 1: true, false: false, 0: false }
const SEARCH_MAX = 100
const MAX_PER_PAGE = 100

function sortFieldOf(raw) {
  const field = raw.replace(/^-/, '').replace(/_([a-z])/g, (match, letter) => letter.toUpperCase())
  return SORTABLE.includes(field) ? field : null
}

function invalid(errors, field, attribute = 'filter') {
  errors[field] = [fieldMessage('validation.invalid', {}, attribute)]
}

function pathsOf(query) {
  const path = query.get('filter[path]')
  if (query.get('filter[cycleId]')) return [ADVANCED]
  return REVIEWED_PATHS.includes(path) ? [path] : REVIEWED_PATHS
}

function statesOf(paths) {
  return [...new Set(paths.flatMap((path) => pathRules(path).queueStates))]
}

function checkFilters(query, errors) {
  if ([...query.keys()].some((key) => key.startsWith('filter[') && !FILTER_KEYS.includes(key))) invalid(errors, 'filter')
  const path = query.get('filter[path]')
  if (path && !REVIEWED_PATHS.includes(path)) invalid(errors, 'filter.path')
  const status = query.get('filter[status]')
  if (status && !statesOf(pathsOf(query)).includes(status)) invalid(errors, 'filter.status')
  const cycleId = query.get('filter[cycleId]')
  if (cycleId && !isPositiveInteger(cycleId)) errors['filter.cycleId'] = [fieldMessage('validation.integer', {}, 'filter')]
  const overdue = query.get('filter[overdue]')
  if (overdue && !(overdue in OVERDUE_VALUES)) invalid(errors, 'filter.overdue')
  const assessorId = query.get('filter[assessorId]')
  if (assessorId && !isPositiveInteger(assessorId)) errors['filter.assessorId'] = [fieldMessage('validation.integer', {}, 'filter')]
}

function checkPaging(query, errors) {
  const sort = query.get('sort')
  if (sort && !sortFieldOf(sort)) invalid(errors, 'sort', 'sort')
  if ((query.get('search') ?? '').length > SEARCH_MAX) errors.search = [fieldMessage('validation.max_chars', { max: SEARCH_MAX }, 'filter')]
  const page = query.get('page')
  if (page && !isPositiveInteger(page)) errors.page = [fieldMessage('validation.integer', {}, 'page')]
  const perPage = query.get('perPage')
  if (perPage && (!isPositiveInteger(perPage) || Number(perPage) > MAX_PER_PAGE)) {
    errors.perPage = [fieldMessage('validation.max', { max: MAX_PER_PAGE }, 'perPage')]
  }
}

export function queueFiltersFrom(query) {
  const errors = {}
  checkFilters(query, errors)
  checkPaging(query, errors)
  throwIfErrors(errors)
  const overdue = query.get('filter[overdue]')
  return {
    paths: pathsOf(query),
    cycleId: query.get('filter[cycleId]') ? Number(query.get('filter[cycleId]')) : null,
    status: query.get('filter[status]') || null,
    overdue: overdue ? OVERDUE_VALUES[overdue] : null,
    assessorId: query.get('filter[assessorId]') ? Number(query.get('filter[assessorId]')) : null,
    search: (query.get('search') ?? '').trim().toLowerCase(),
    sort: query.get('sort') || '-submitted_at',
  }
}

function matchesSearch(record, needle, normalize) {
  if (!needle) return true
  const entity = entityOf(record)
  const target = normalize(needle)
  return [record.number, entity.name.ar, entity.name.en, entity.owner.email].some((value) => normalize(value ?? '').includes(target))
}

function compareBy(field, descending) {
  return (left, right) => {
    const a = left[field]
    const b = right[field]
    if (a === b) return right.id - left.id
    if (a === null) return descending ? 1 : -1
    if (b === null) return descending ? -1 : 1
    const order = a < b ? -1 : 1
    return descending ? -order : order
  }
}

function matchesFilters(record, filters) {
  if (!filters.paths.includes(record.path)) return false
  if (filters.cycleId !== null && record.awardCycleId !== filters.cycleId) return false
  if (filters.status && record.status !== filters.status) return false
  if (filters.overdue !== null && isOverdue(record) !== filters.overdue) return false
  return filters.assessorId === null || activeAssignment(record)?.assessorId === filters.assessorId
}

export function queueRecords(filters, normalize) {
  return reviewedRecords()
    .filter((record) => pathRules(record.path).inQueue(record.status) && !isFrozen(record))
    .filter((record) => matchesFilters(record, filters) && matchesSearch(record, filters.search, normalize))
    .sort(compareBy(sortFieldOf(filters.sort), filters.sort.startsWith('-')))
}

function wireKeyOf(status) {
  return status.replace(/-([a-z])/g, (match, letter) => letter.toUpperCase())
}

export function queueStats(records, filters) {
  return {
    total: records.length,
    byStatus: Object.fromEntries(
      statesOf(filters.paths).map((status) => [wireKeyOf(status), records.filter((record) => record.status === status).length]),
    ),
    overdue: records.filter((record) => isOverdue(record)).length,
  }
}

export function queuePage(records, query) {
  return paginate(records, query)
}
