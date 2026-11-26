import { paginate } from '../../contract/pagination'
import { fieldMessage } from '../assessments/assessmentRejection'
import { entityOf, isFrozen } from '../assessments/assessmentsStore'
import { activeAssignment, isOverdue, reviewedRecords } from './accreditedRecords'
import { REVIEWED_PATHS, pathRules } from '../assessments/reviewedPaths'
import { isPositiveInteger, throwIfErrors } from './accreditedValidation'
import { pathLabelOf } from '../../fixtures/pathLabels'

const FILTER_KEYS = [
  'filter[path]',
  'filter[stage]',
  'filter[status]',
  'filter[overdue]',
  'filter[assessorId]',
  'filter[cycleId]',
]
const ADVANCED = 'advanced'
const SORTABLE = ['submittedAt', 'dueAt', 'status', 'createdAt']
const OVERDUE_VALUES = { true: true, 1: true, false: false, 0: false }
const SEARCH_MAX = 100
const PREPARING_STATE = 'draft'
const MAX_PER_PAGE = 100

// The request list's stages (admin review 2026-11-20), as the API defines them.
const STAGES = {
  all: (record, rules) => record.status === PREPARING_STATE || rules.inQueue(record.status),
  preparing: (record) => record.status === PREPARING_STATE,
  awaiting_assignment: (record, rules) => record.status === rules.stages.awaiting,
  in_progress: (record, rules) =>
    rules.inQueue(record.status) &&
    rules.isOpen(record.status) &&
    record.status !== rules.stages.awaiting,
  overdue: (record) => isOverdue(record),
  closed: (record, rules) => rules.inQueue(record.status) && !rules.isOpen(record.status),
}
const COUNTED_STAGES = ['preparing', 'awaiting_assignment', 'in_progress', 'closed']

function inStage(record, stage) {
  return STAGES[stage](record, pathRules(record.path))
}

function sortFieldOf(raw) {
  const field = raw.replace(/^-/, '').replace(/_([a-z])/g, (match, letter) => letter.toUpperCase())
  return SORTABLE.includes(field) ? field : null
}

function invalid(errors, field, attribute = 'filter') {
  errors[field] = [fieldMessage('validation.invalid', {}, attribute)]
}

function pathsOf(query, followed) {
  const path = query.get('filter[path]')
  if (query.get('filter[cycleId]')) return [ADVANCED]
  return [followed.includes(path) ? path : followed[0]]
}

function statesOf(paths) {
  return [PREPARING_STATE, ...new Set(paths.flatMap((path) => pathRules(path).queueStates))]
}

function checkFilters(query, followed, errors) {
  if ([...query.keys()].some((key) => key.startsWith('filter[') && !FILTER_KEYS.includes(key)))
    invalid(errors, 'filter')
  const path = query.get('filter[path]')
  if (path && !followed.includes(path)) invalid(errors, 'filter.path')
  const stage = query.get('filter[stage]')
  if (stage && !Object.hasOwn(STAGES, stage)) invalid(errors, 'filter.stage')
  const status = query.get('filter[status]')
  if (status && !statesOf(pathsOf(query, followed)).includes(status))
    invalid(errors, 'filter.status')
  const cycleId = query.get('filter[cycleId]')
  if (cycleId && !followed.includes(ADVANCED)) invalid(errors, 'filter.cycleId')
  else if (cycleId && !isPositiveInteger(cycleId))
    errors['filter.cycleId'] = [fieldMessage('validation.integer', {}, 'filter')]
  const overdue = query.get('filter[overdue]')
  if (overdue && !(overdue in OVERDUE_VALUES)) invalid(errors, 'filter.overdue')
  const assessorId = query.get('filter[assessorId]')
  if (assessorId && !isPositiveInteger(assessorId))
    errors['filter.assessorId'] = [fieldMessage('validation.integer', {}, 'filter')]
}

function checkPaging(query, errors) {
  const sort = query.get('sort')
  if (sort && !sortFieldOf(sort)) invalid(errors, 'sort', 'sort')
  if ((query.get('search') ?? '').length > SEARCH_MAX)
    errors.search = [fieldMessage('validation.max_chars', { max: SEARCH_MAX }, 'filter')]
  const page = query.get('page')
  if (page && !isPositiveInteger(page))
    errors.page = [fieldMessage('validation.integer', {}, 'page')]
  const perPage = query.get('perPage')
  if (perPage && (!isPositiveInteger(perPage) || Number(perPage) > MAX_PER_PAGE)) {
    errors.perPage = [fieldMessage('validation.max', { max: MAX_PER_PAGE }, 'perPage')]
  }
}

export function queueFiltersFrom(query, followed = REVIEWED_PATHS) {
  const errors = {}
  checkFilters(query, followed, errors)
  checkPaging(query, errors)
  throwIfErrors(errors)
  const overdue = query.get('filter[overdue]')
  return {
    paths: pathsOf(query, followed),
    stage: query.get('filter[stage]') || 'all',
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
  return [record.number, entity.name.ar, entity.name.en, entity.owner.email].some((value) =>
    normalize(value ?? '').includes(target),
  )
}

// A draft has no submission date yet: it sorts by its opening date. Rows with no due date sort last.
function sortValueOf(record, field) {
  return field === 'submittedAt' ? (record.submittedAt ?? record.createdAt) : record[field]
}

function compareBy(field, descending) {
  return (left, right) => {
    const a = sortValueOf(left, field)
    const b = sortValueOf(right, field)
    if (a === b) return right.id - left.id
    if (a === null || a === undefined) return 1
    if (b === null || b === undefined) return -1
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

function listedRecords() {
  return reviewedRecords().filter((record) => inStage(record, 'all') && !isFrozen(record))
}

function queuedRecords() {
  return reviewedRecords().filter(
    (record) => pathRules(record.path).inQueue(record.status) && !isFrozen(record),
  )
}

function preparingAll() {
  return listedRecords().filter((record) => record.status === PREPARING_STATE)
}

export function queuePathCounts(followed) {
  const queued = queuedRecords()
  const preparing = preparingAll()
  return followed.map((path) => {
    const records = queued.filter((record) => record.path === path)
    return {
      path,
      pathLabel: pathLabelOf(path),
      total: records.length,
      awaitingAssignment: records.filter(
        (record) => record.status === pathRules(path).stages.awaiting,
      ).length,
      overdue: records.filter((record) => isOverdue(record)).length,
      preparing: preparing.filter((record) => record.path === path).length,
    }
  })
}

// Every request matching the filters over all stages; the page then keeps the chosen stage.
export function queueRecords(filters, normalize) {
  return listedRecords()
    .filter(
      (record) =>
        matchesFilters(record, filters) && matchesSearch(record, filters.search, normalize),
    )
    .sort(compareBy(sortFieldOf(filters.sort), filters.sort.startsWith('-')))
}

export function inChosenStage(records, filters) {
  return records.filter((record) => inStage(record, filters.stage))
}

function wireKeyOf(status) {
  return status.replace(/-([a-z])/g, (match, letter) => letter.toUpperCase())
}

function stageCountsOf(records) {
  return Object.fromEntries([
    ...COUNTED_STAGES.map((stage) => [
      stage,
      records.filter((record) => inStage(record, stage)).length,
    ]),
    ['overdue', records.filter((record) => isOverdue(record)).length],
  ])
}

export function queueStats(records, filters) {
  return {
    total: records.length,
    byStatus: Object.fromEntries(
      statesOf(filters.paths).map((status) => [
        wireKeyOf(status),
        records.filter((record) => record.status === status).length,
      ]),
    ),
    byStage: stageCountsOf(records),
    overdue: records.filter((record) => isOverdue(record)).length,
  }
}

export function queuePage(records, query) {
  return paginate(records, query)
}
