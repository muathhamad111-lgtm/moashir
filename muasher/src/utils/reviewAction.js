import { ACCREDITED_STATUSES } from '@/constants/accredited'
import { ADVANCED_STATUSES } from '@/constants/advanced'
import { REVIEW_OVERRIDE_QUERY } from '@/constants/review'
import { ROUTE_NAMES } from '@/constants/routeNames'

const NOT_STARTED = [ACCREDITED_STATUSES.ASSIGNED]
const UNDER_WAY = [
  ACCREDITED_STATUSES.UNDER_REVIEW,
  ACCREDITED_STATUSES.NEEDS_COMPLETION,
  ADVANCED_STATUSES.UNDER_INITIAL_ASSESSMENT,
]

// The assessor's review action reads the same in the assigned list and on the request page.
export function reviewActionKey(status) {
  if (NOT_STARTED.includes(status)) return 'start'
  if (UNDER_WAY.includes(status)) return 'continue'
  return 'viewReview'
}

// The assigned list and the request page open the same review for every path, so both take it from here.
export function reviewRoute(requestId) {
  return { name: ROUTE_NAMES.REQUESTS_REVIEW, params: { id: requestId } }
}

// The score override is an edit mode of the review (design §B.3.5); the review still checks the viewer may override.
export function overrideReviewRoute(requestId) {
  return { ...reviewRoute(requestId), query: { [REVIEW_OVERRIDE_QUERY]: '1' } }
}
