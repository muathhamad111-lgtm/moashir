// Mirrors backend AssignedRequestsOrdering: an assessor's assigned requests by due date (soonest
// first by default), by when this assessor was assigned, or by status in workflow order; the
// request id breaks ties in the same direction.
const DESCENDING = '-'
const COLUMNS = ['due_at', 'assigned_at', 'status']

export function isAssignedSort(sort) {
  return !sort || COLUMNS.includes(sort.replace(DESCENDING, ''))
}

function assignedAtOf(record, viewerId) {
  const mine = record.assignments.filter((item) => item.assessorId === viewerId)
  return (
    mine
      .map((item) => item.createdAt ?? '')
      .sort()
      .at(-1) ?? ''
  )
}

function keyOf(column, statusOrder, viewerId) {
  if (column === 'assigned_at') return (record) => assignedAtOf(record, viewerId)
  if (column === 'status') return (record) => statusOrder.indexOf(record.status)
  return (record) => record.dueAt ?? '￿'
}

export function sortAssigned(records, { sort, statusOrder, viewerId }) {
  const descending = Boolean(sort?.startsWith(DESCENDING))
  const column = sort ? sort.replace(DESCENDING, '') : 'due_at'
  const valueOf = keyOf(column, statusOrder, viewerId)
  const sign = descending ? -1 : 1
  return [...records].sort((left, right) => {
    const a = valueOf(left)
    const b = valueOf(right)
    if (a !== b) return (a < b ? -1 : 1) * sign
    return (left.id - right.id) * sign
  })
}
