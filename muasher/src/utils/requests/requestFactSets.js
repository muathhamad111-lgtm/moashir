import { ACCREDITED_STATUSES as ACC } from '@/constants/accredited'
import { ADVANCED_STATUSES as ADV } from '@/constants/advanced'
import { ASSESSMENT_PATHS } from '@/constants/assessment'

// The facts a staff request record shows, per path and state (operator review 2026-11-25, layout A). The summary
// card holds two titled groups — who holds the request (assignment) and its dates (timeline) — and the overview
// tab's «بيانات الطلب» card holds the rest (details). A fact that does not apply is not shown.
export const REQUEST_FACTS = Object.freeze({
  ENTITY: 'entity',
  STATUS: 'status',
  ASSESSOR: 'assessor',
  OPENED: 'openedAt',
  UPDATED: 'updatedAt',
  SUBMITTED: 'submittedAt',
  DUE: 'dueAt',
  ASSIGNED: 'assignedAt',
  DEADLINE: 'completionDeadline',
  COMPLETED: 'completedAt',
  FEE: 'fee',
  LEVEL: 'entityLevel',
  ASSIGNMENTS: 'assignments',
  COMPLETIONS: 'completions',
  CYCLE: 'cycle',
  REGION: 'region',
})

const F = REQUEST_FACTS

function set(assignment, timeline, details) {
  return Object.freeze({ assignment, timeline, details })
}

const ACCREDITED_DRAFT = set([], [F.OPENED, F.UPDATED], [F.LEVEL, F.FEE])
const ACCREDITED_REVIEWING = set(
  [F.ASSESSOR, F.ASSIGNED],
  [F.SUBMITTED, F.DUE],
  [F.LEVEL, F.FEE, F.ASSIGNMENTS],
)
const ACCREDITED_CLOSED = set([F.ASSESSOR], [F.SUBMITTED], [F.LEVEL, F.FEE, F.ASSIGNMENTS])

const ACCREDITED_SETS = {
  [ACC.DRAFT]: ACCREDITED_DRAFT,
  [ACC.AWAITING_PAYMENT]: ACCREDITED_DRAFT,
  [ACC.SUBMITTED]: set([F.ASSESSOR], [F.SUBMITTED, F.DUE], [F.LEVEL, F.FEE]),
  [ACC.ASSIGNED]: ACCREDITED_REVIEWING,
  [ACC.UNDER_REVIEW]: ACCREDITED_REVIEWING,
  [ACC.NEEDS_COMPLETION]: set(
    [F.ASSESSOR, F.ASSIGNED],
    [F.SUBMITTED, F.DUE, F.DEADLINE],
    [F.LEVEL, F.FEE, F.ASSIGNMENTS, F.COMPLETIONS],
  ),
  [ACC.COMPLETED]: set(
    [F.ASSESSOR, F.ASSIGNED],
    [F.SUBMITTED, F.COMPLETED],
    [F.LEVEL, F.FEE, F.ASSIGNMENTS, F.COMPLETIONS],
  ),
  [ACC.REFUNDED]: ACCREDITED_CLOSED,
  [ACC.DECLINED]: ACCREDITED_CLOSED,
}

// The advanced path is being merged into the award (operator, 2026-11-25). Its award stages (desk, field visit,
// judging, award decision) join these groups when the award build resumes; until then they are not shown at all.
const ADVANCED_REVIEWING = set(
  [F.ASSESSOR, F.ASSIGNED],
  [F.SUBMITTED, F.DUE],
  [F.CYCLE, F.REGION, F.LEVEL, F.ASSIGNMENTS],
)
const ADVANCED_SCREENED = set([F.ASSESSOR], [F.SUBMITTED], [F.CYCLE, F.REGION, F.LEVEL])
const ADVANCED_SETS = {
  [ADV.AWAITING_ASSIGNMENT]: set([F.ASSESSOR], [F.SUBMITTED], [F.CYCLE, F.REGION, F.LEVEL]),
  [ADV.ASSIGNED]: ADVANCED_REVIEWING,
  [ADV.UNDER_INITIAL_ASSESSMENT]: ADVANCED_REVIEWING,
  [ADV.UNDER_REVIEW]: ADVANCED_REVIEWING,
  [ADV.NEEDS_COMPLETION]: set(
    [F.ASSESSOR, F.ASSIGNED],
    [F.SUBMITTED, F.DUE, F.DEADLINE],
    [F.CYCLE, F.REGION, F.LEVEL, F.COMPLETIONS],
  ),
  [ADV.AWAITING_FILTERING]: ADVANCED_SCREENED,
  [ADV.QUALIFIED]: ADVANCED_SCREENED,
  [ADV.NOT_QUALIFIED]: ADVANCED_SCREENED,
  [ADV.WITHDRAWN]: ADVANCED_SCREENED,
  [ADV.DECLINED]: ADVANCED_SCREENED,
  [ADV.EXPIRED]: ADVANCED_SCREENED,
}

const FALLBACK = set([F.ASSESSOR], [F.SUBMITTED, F.DUE], [F.LEVEL])

export function requestFactSetOf({ path, status }) {
  const sets = path === ASSESSMENT_PATHS.ACCREDITED ? ACCREDITED_SETS : ADVANCED_SETS
  return sets[status] ?? FALLBACK
}

export const REQUEST_FACT_LABELS = Object.freeze({
  [F.ENTITY]: 'requests.columns.entity',
  [F.STATUS]: 'requests.columns.status',
  [F.ASSESSOR]: 'requests.columns.assessor',
  [F.OPENED]: 'requests.detail.openedAt',
  [F.UPDATED]: 'requests.detail.facts.updatedAt',
  [F.SUBMITTED]: 'requests.columns.submittedAt',
  [F.DUE]: 'requests.columns.dueAt',
  [F.ASSIGNED]: 'requests.detail.assignedAt',
  [F.DEADLINE]: 'accredited.completion.deadline',
  [F.COMPLETED]: 'requests.detail.completedAt',
  [F.FEE]: 'requests.detail.payment',
  [F.LEVEL]: 'requests.detail.entityLevel',
  [F.ASSIGNMENTS]: 'requests.detail.facts.assignments',
  [F.COMPLETIONS]: 'requests.detail.facts.completions',
  [F.CYCLE]: 'requests.columns.cycle',
  [F.REGION]: 'requests.columns.region',
})

// A fact with nothing to say for this request is left out: no «غير محدد» rows. The fee is left out of the details
// when the payment tab already shows it.
const APPLIES = {
  [F.OPENED]: (d) => Boolean(d.record?.createdAt),
  [F.UPDATED]: (d) => Boolean(d.record?.updatedAt),
  [F.SUBMITTED]: (d) => Boolean(d.submittedAt),
  [F.DUE]: (d) => Boolean(d.dueAt),
  [F.ASSIGNED]: (d) => Boolean(d.assignedAt),
  [F.DEADLINE]: (d) => Boolean(d.completionDeadline),
  [F.COMPLETED]: (d) => Boolean(d.completedAt),
  [F.FEE]: (d) => !d.payment.details,
  [F.LEVEL]: (d) => Boolean(d.entity),
  [F.ASSIGNMENTS]: (d) => d.assignmentHistory.length > 0,
  [F.COMPLETIONS]: (d) => d.completionRequests.length > 0,
  [F.CYCLE]: (d) => Boolean(d.awardCycle),
  [F.REGION]: (d) => Boolean(d.region),
}

export function requestFactsOf(detail) {
  const { assignment, timeline, details } = requestFactSetOf(detail)
  const applies = (fact) => APPLIES[fact]?.(detail) ?? true
  return {
    assignment: assignment.filter(applies),
    timeline: timeline.filter(applies),
    details: details.filter(applies),
  }
}

// The request at a glance where there is no summary card (the review page's «الطلب» tab): who it is, then the same
// groups as the summary card, then the details. The entity's level shows beside its name there, not again below.
export function requestFactGroupsOf(detail) {
  const { assignment, timeline, details } = requestFactsOf(detail)
  return [
    { key: 'request', facts: [F.ENTITY, F.STATUS] },
    { key: 'assignment', facts: assignment },
    { key: 'timeline', facts: timeline },
    { key: 'details', facts: details.filter((fact) => fact !== F.LEVEL) },
  ].filter((group) => group.facts.length)
}

// The date the request entered its current state, for the status's «منذ».
export function requestStatusSince(detail) {
  return (
    detail.record?.statusHistory?.at(-1)?.at ??
    detail.submittedAt ??
    detail.record?.createdAt ??
    null
  )
}
