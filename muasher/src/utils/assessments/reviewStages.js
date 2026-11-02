import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { ACCREDITED_STATUSES, REASSIGNABLE_STATUSES } from '@/constants/accredited'
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
    reassignable: [ADVANCED_STATUSES.ASSIGNED, ADVANCED_STATUSES.UNDER_INITIAL_ASSESSMENT],
  },
}

export function stagesFor(path) {
  return STAGES[path] ?? STAGES[ASSESSMENT_PATHS.ACCREDITED]
}

export function isAdvancedPath(path) {
  return path === ASSESSMENT_PATHS.ADVANCED
}

export function isAssignable(request) {
  return request.status === stagesFor(request.path).awaiting
}

export function isReassignable(request) {
  return stagesFor(request.path).reassignable.includes(request.status)
}

export function isReturnable(request) {
  return isAdvancedPath(request.path) && request.status === ADVANCED_STATUSES.AWAITING_ASSIGNMENT
}

export function isWithdrawable(request) {
  return isAdvancedPath(request.path) && ADVANCED_WITHDRAWABLE_STATUSES.includes(request.status)
}
