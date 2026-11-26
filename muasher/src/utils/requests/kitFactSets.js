// The facts a Kit request record shows, per state (request page v4.1): who evaluates it, then its dates. A date the
// request has not reached yet is not shown; the evaluator always is («غير مُسند» until one is assigned).
export const KIT_FACT_LABELS = Object.freeze({
  evaluator: 'bagRequests.columns.evaluator',
  paidAt: 'bagRequests.columns.paidAt',
  assignedAt: 'bagRequests.columns.assignedAt',
  startedAt: 'bagRequests.columns.startedAt',
  completedAt: 'bag.manage.completedAt',
})

const KIT_REVIEWING = Object.freeze({
  assignment: ['evaluator', 'assignedAt'],
  timeline: ['paidAt', 'startedAt'],
})
const KIT_FACT_SETS = Object.freeze({
  'awaiting-assignment': { assignment: ['evaluator'], timeline: ['paidAt'] },
  completed: { assignment: ['evaluator', 'assignedAt'], timeline: ['paidAt', 'completedAt'] },
})

// The date the Kit entered its current state, for the status's «منذ».
const KIT_SINCE = Object.freeze({
  'awaiting-assignment': 'paidAt',
  assigned: 'assignedAt',
  completed: 'completedAt',
})

export function kitFactsOf(request) {
  const set = KIT_FACT_SETS[request.status] ?? KIT_REVIEWING
  const applies = (key) => key === 'evaluator' || Boolean(request[key])
  return { assignment: set.assignment.filter(applies), timeline: set.timeline.filter(applies) }
}

export function kitStatusSince(request) {
  return request[KIT_SINCE[request.status] ?? 'startedAt'] ?? request.paidAt ?? null
}
