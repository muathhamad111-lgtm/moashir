import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { paginate } from '../../contract/pagination'
import { filteredAssessments } from '../assessments/assessmentQueries'
import { assessmentResource } from '../assessments/selfSerializers'
import { missedRowsOf } from './requestPeriods'

const MERGING_SORTS = ['-created_at', 'created_at']
const ENTITY_ROLE = 'entity'

function includesMissedRows(user, filters) {
  return (
    filters.path === ASSESSMENT_PATHS.ACCREDITED &&
    user.roles.includes(ENTITY_ROLE) &&
    Boolean(user.entityId) &&
    !filters.status &&
    filters.isOpen === null &&
    filters.hasResult === null &&
    MERGING_SORTS.includes(filters.sort)
  )
}

function byInstant(descending) {
  return (left, right) => {
    if (left.sortAt === right.sortAt) return left.row.rowKey < right.row.rowKey ? -1 : 1
    const order = left.sortAt < right.sortAt ? -1 : 1
    return descending ? -order : order
  }
}

function mergedRows(user, filters) {
  const requests = filteredAssessments(user, filters).map((record) => ({
    sortAt: record.createdAt,
    row: assessmentResource(record),
  }))
  const missed = missedRowsOf(Number(user.entityId)).map(({ sortAt, ...row }) => ({ sortAt, row }))
  return [...requests, ...missed]
    .sort(byInstant(filters.sort.startsWith('-')))
    .map((entry) => entry.row)
}

export function listPathRows(user, filters, query) {
  if (!includesMissedRows(user, filters)) {
    const rows = filteredAssessments(user, filters).map(assessmentResource)
    return { ...paginate(rows, query), missedPeriods: 0 }
  }
  const rows = mergedRows(user, filters)
  const missedPeriods = rows.filter((row) => row.rowType === 'missed_period').length
  return { ...paginate(rows, query), missedPeriods }
}
