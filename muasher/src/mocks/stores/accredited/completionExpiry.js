import { isFrozen } from '../assessments/assessmentsStore'
import { ACTORS, transition } from '../assessments/statusHistory'
import { isAdvanced } from '../assessments/reviewedPaths'
import { ADVANCED_STATES } from '../award/advancedStates'
import { announceAdvancedExpiry } from '../award/awardNotices'
import { STATES } from './accreditedStates'
import { activeAssignment, nowIso, openCompletionOf, reviewedRecords } from './accreditedRecords'
import { announceCompletionExpired } from './accreditedNotices'

const OUTCOMES = {
  continue_as_submitted: { completion: 'expired-continued', accredited: STATES.UNDER_REVIEW, advanced: ADVANCED_STATES.AWAITING_ASSIGNMENT, endsAssignment: false },
  decline: { completion: 'expired-declined', accredited: STATES.DECLINED, advanced: ADVANCED_STATES.DECLINED, endsAssignment: true },
}

function expire(record, completion, at) {
  const outcome = OUTCOMES[completion.expiryOutcome]
  const status = isAdvanced(record) ? outcome.advanced : outcome.accredited
  Object.assign(completion, { status: outcome.completion, resolvedAt: at })
  if (outcome.endsAssignment) Object.assign(activeAssignment(record) ?? {}, { status: 'ended', endedAt: at, endReason: 'declined_incomplete' })
  record.transitions.push(transition(STATES.NEEDS_COMPLETION, status, 'expire_completion', at, ACTORS.SYSTEM))
  Object.assign(record, { status, updatedAt: at })
  if (isAdvanced(record)) announceAdvancedExpiry(record, completion.expiryOutcome)
  else announceCompletionExpired(record, completion.expiryOutcome)
}

export function settleExpiredCompletions(now = Date.now()) {
  reviewedRecords()
    .filter((record) => record.status === STATES.NEEDS_COMPLETION && !isFrozen(record))
    .forEach((record) => {
      const completion = openCompletionOf(record)
      if (completion && new Date(completion.deadlineAt).getTime() <= now) expire(record, completion, nowIso())
    })
}
