import { ACCREDITED_STATUSES } from '@/constants/accredited'
import { ADVANCED_STATUSES } from '@/constants/advanced'

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
