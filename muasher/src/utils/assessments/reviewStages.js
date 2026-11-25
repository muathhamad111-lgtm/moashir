import { ASSESSMENT_PATHS } from '@/constants/assessment'
import {
  ACCREDITED_STATUSES,
  ASSIGNMENT_MODES,
  ASSIGNMENT_STATUSES,
  REASSIGNABLE_STATUSES,
} from '@/constants/accredited'
import { ADVANCED_STATUSES, ADVANCED_WITHDRAWABLE_STATUSES } from '@/constants/advanced'

const STAGES = {
  [ASSESSMENT_PATHS.ACCREDITED]: {
    awaiting: ACCREDITED_STATUSES.SUBMITTED,
    assigned: ACCREDITED_STATUSES.ASSIGNED,
    reviewing: ACCREDITED_STATUSES.UNDER_REVIEW,
    done: ACCREDITED_STATUSES.COMPLETED,
    reassignable: REASSIGNABLE_STATUSES,
  },
  [ASSESSMENT_PATHS.ADVANCED]: {
    awaiting: ADVANCED_STATUSES.AWAITING_ASSIGNMENT,
    assigned: ADVANCED_STATUSES.ASSIGNED,
    reviewing: ADVANCED_STATUSES.UNDER_INITIAL_ASSESSMENT,
    done: ADVANCED_STATUSES.AWAITING_FILTERING,
    // Operator 2026-11-23: also while the entity completes the request, when it has an assessor.
    reassignable: [
      ADVANCED_STATUSES.ASSIGNED,
      ADVANCED_STATUSES.UNDER_INITIAL_ASSESSMENT,
      ADVANCED_STATUSES.NEEDS_COMPLETION,
    ],
  },
}

export function stagesFor(path) {
  return STAGES[path] ?? STAGES[ASSESSMENT_PATHS.ACCREDITED]
}

// The review is over once the request left the assessor's stages (completed, closed, or past the
// initial assessment): the page then shows the final result, with nothing left to score.
export function isReviewFinished(assessment) {
  if (!assessment) return false
  const stages = stagesFor(assessment.path)
  const working = [
    stages.awaiting,
    stages.assigned,
    stages.reviewing,
    ACCREDITED_STATUSES.NEEDS_COMPLETION,
  ]
  return !working.includes(assessment.status)
}

export function isAdvancedPath(path) {
  return path === ASSESSMENT_PATHS.ADVANCED
}

export function isAssignable(request) {
  return request.status === stagesFor(request.path).awaiting
}

// A queue row names its assessor; the request page lists the assignment history.
const hasActiveAssessor = (request) =>
  Boolean(request.assessor) ||
  (request.assignmentHistory ?? []).some(
    (assignment) => assignment.status === ASSIGNMENT_STATUSES.ACTIVE,
  )

// A request the entity is completing may have been returned before it had an assessor to hand over.
export function isReassignable(request) {
  if (!stagesFor(request.path).reassignable.includes(request.status)) return false
  return request.status !== ADVANCED_STATUSES.NEEDS_COMPLETION || hasActiveAssessor(request)
}

export function assignmentModeOf(request) {
  if (request.isFrozen) return null
  if (isAssignable(request)) return ASSIGNMENT_MODES.ASSIGN
  if (isReassignable(request)) return ASSIGNMENT_MODES.REASSIGN
  return null
}

// The follower returns an advanced request for completion before or after it is assigned
// (operator 2026-11-22); after assignment the resubmission goes back to the same assessor.
const RETURNABLE_STATUSES = [
  ADVANCED_STATUSES.AWAITING_ASSIGNMENT,
  ADVANCED_STATUSES.ASSIGNED,
  ADVANCED_STATUSES.UNDER_INITIAL_ASSESSMENT,
]

export function isReturnable(request) {
  return isAdvancedPath(request.path) && RETURNABLE_STATUSES.includes(request.status)
}

export function isWithdrawable(request) {
  return isAdvancedPath(request.path) && ADVANCED_WITHDRAWABLE_STATUSES.includes(request.status)
}
