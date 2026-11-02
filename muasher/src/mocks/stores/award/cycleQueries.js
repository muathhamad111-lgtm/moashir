import { fieldMessage } from '../assessments/assessmentRejection'
import { isPositiveInteger, throwIfErrors } from '../accredited/accreditedValidation'
import { allCycles } from './cycleStore'
import { canView } from './cycleAccess'

const STATUSES = ['draft', 'submission-open', 'submission-closed', 'committee-assessment', 'awaiting-secretary', 'approved', 'announced', 'closed']
const SORTABLE = ['submissionStart', 'announcementAt', 'createdAt']
const SEARCH_MAX = 100
const MAX_PER_PAGE = 100

function sortFieldOf(raw) {
  const field = raw.replace(/^-/, '').replace(/_([a-z])/g, (match, letter) => letter.toUpperCase())
  return SORTABLE.includes(field) ? field : null
}

function invalid(errors, field, attribute = 'filter') {
  errors[field] = [fieldMessage('validation.invalid', {}, attribute)]
}

export function cycleFiltersFrom(query) {
  const errors = {}
  if ([...query.keys()].some((key) => key.startsWith('filter[') && key !== 'filter[status]')) invalid(errors, 'filter')
  const status = query.get('filter[status]')
  if (status && !STATUSES.includes(status)) invalid(errors, 'filter.status')
  const sort = query.get('sort')
  if (sort && !sortFieldOf(sort)) invalid(errors, 'sort', 'sort')
  if ((query.get('search') ?? '').length > SEARCH_MAX) errors.search = [fieldMessage('validation.max_chars', { max: SEARCH_MAX }, 'filter')]
  const perPage = query.get('perPage')
  if (perPage && (!isPositiveInteger(perPage) || Number(perPage) > MAX_PER_PAGE)) errors.perPage = [fieldMessage('validation.max', { max: MAX_PER_PAGE }, 'perPage')]
  throwIfErrors(errors)
  return { status: status || null, search: (query.get('search') ?? '').trim(), sort: sort || '-submission_start' }
}

function matchesSearch(cycle, needle, normalize) {
  if (!needle) return true
  const target = normalize(needle)
  return [cycle.name.ar, cycle.name.en].some((value) => normalize(value).includes(target))
}

function compareBy(field, descending) {
  return (left, right) => {
    const a = left[field]
    const b = right[field]
    if (a === b) return right.id - left.id
    if (a === null) return 1
    if (b === null) return -1
    return (a < b ? -1 : 1) * (descending ? -1 : 1)
  }
}

export function visibleCycles(user, filters, normalize) {
  return allCycles()
    .filter((cycle) => canView(user, cycle))
    .filter((cycle) => matchesSearch(cycle, filters.search, normalize))
}

export function listedCycles(user, filters, normalize) {
  return visibleCycles(user, filters, normalize)
    .filter((cycle) => !filters.status || cycle.status === filters.status)
    .sort(compareBy(sortFieldOf(filters.sort), filters.sort.startsWith('-')))
}

function wireKeyOf(status) {
  return status.replace(/-([a-z])/g, (match, letter) => letter.toUpperCase())
}

export function cycleStats(user, filters, normalize) {
  const cycles = visibleCycles(user, filters, normalize)
  const byStatus = Object.fromEntries(STATUSES.map((status) => [wireKeyOf(status), cycles.filter((cycle) => cycle.status === status).length]))
  return { total: cycles.length, byStatus }
}
