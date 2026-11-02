import { ASSESSMENT_PATHS, SELF_STATUSES } from '@/constants/assessment'
import { STATES as ACCREDITED_STATES } from '../accredited/accreditedStates'
import { paginate } from '../../contract/pagination'
import { assessmentReject, fieldMessage } from './assessmentRejection'
import { allAssessments, entityOf, isFrozen } from './assessmentsStore'
import { inRecordScope, isReviewedBy, readsEveryAssessment } from './assessmentAccess'

const SORTABLE = ['createdAt', 'submittedAt', 'completedAt']
const FILTER_KEYS = ['filter[path]', 'filter[status]', 'filter[entityId]']
const POSITIVE_INTEGER = /^[1-9]\d*$/
const MAX_PER_PAGE = 100
const KNOWN_PATHS = Object.values(ASSESSMENT_PATHS)
const KNOWN_STATUSES = [...new Set([...Object.values(SELF_STATUSES), ...Object.values(ACCREDITED_STATES)])]

function sortFieldOf(raw) {
  const field = raw.replace(/^-/, '').replace(/_([a-z])/g, (match, letter) => letter.toUpperCase())
  return SORTABLE.includes(field) ? field : null
}

function invalid(errors, field, attribute = field) {
  errors[field] = [fieldMessage('validation.invalid', {}, attribute)]
}

function checkFilters(query, errors) {
  const unknown = [...query.keys()].some((key) => key.startsWith('filter[') && !FILTER_KEYS.includes(key))
  if (unknown) invalid(errors, 'filter')
  const path = query.get('filter[path]')
  if (path && !KNOWN_PATHS.includes(path)) invalid(errors, 'filter.path', 'filter')
  const status = query.get('filter[status]')
  if (status && !KNOWN_STATUSES.includes(status)) invalid(errors, 'filter.status', 'filter')
  const entityId = query.get('filter[entityId]')
  if (entityId && !POSITIVE_INTEGER.test(entityId)) {
    errors['filter.entityId'] = [fieldMessage('validation.integer', {}, 'filter')]
  }
}

function checkPaging(query, errors) {
  const sort = query.get('sort')
  if (sort && !sortFieldOf(sort)) invalid(errors, 'sort')
  const page = query.get('page')
  if (page && !POSITIVE_INTEGER.test(page)) errors.page = [fieldMessage('validation.integer', {}, 'page')]
  const perPage = query.get('perPage')
  if (perPage && (!POSITIVE_INTEGER.test(perPage) || Number(perPage) > MAX_PER_PAGE)) {
    errors.perPage = [fieldMessage('validation.max', { max: MAX_PER_PAGE }, 'perPage')]
  }
}

export function listFiltersFrom(query) {
  const errors = {}
  checkFilters(query, errors)
  checkPaging(query, errors)
  if (Object.keys(errors).length) throw assessmentReject.validation(errors)
  return {
    path: query.get('filter[path]') || null,
    status: query.get('filter[status]') || null,
    entityId: query.get('filter[entityId]') ? Number(query.get('filter[entityId]')) : null,
    sort: query.get('sort') || '-created_at',
  }
}

function compareBy(field, descending) {
  return (left, right) => {
    const a = left[field]
    const b = right[field]
    if (a === b) return right.id - left.id
    if (a === null) return descending ? -1 : 1
    if (b === null) return descending ? 1 : -1
    const order = a < b ? -1 : 1
    return descending ? -order : order
  }
}

export function listAssessments(user, filters, query) {
  const rows = allAssessments()
    .filter((record) => inRecordScope(user, record))
    .filter((record) => (filters.entityId === null ? !isFrozen(record) : record.entityId === filters.entityId))
    .filter((record) => !filters.path || record.path === filters.path)
    .filter((record) => !filters.status || record.status === filters.status)
    .sort(compareBy(sortFieldOf(filters.sort), filters.sort.startsWith('-')))
  return paginate(rows, query)
}

function matchesNeedle(record, needle, normalize) {
  const entity = entityOf(record)
  return [record.number, entity.name.ar, entity.name.en, entity.owner.email].some((value) =>
    normalize(value ?? '').includes(needle),
  )
}

export function searchAssessmentRequests(user, needle, normalize) {
  const scoped = readsEveryAssessment(user) ? allAssessments() : allAssessments().filter((record) => isReviewedBy(user, record))
  return scoped
    .filter((record) => !isFrozen(record) && matchesNeedle(record, needle, normalize))
    .sort(compareBy('createdAt', true))
}
