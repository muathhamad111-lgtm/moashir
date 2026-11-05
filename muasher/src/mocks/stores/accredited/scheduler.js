import { sweepPaidAccreditedPayments } from './accreditedOpening'
import { settleExpiredCompletions } from './completionExpiry'
import { runAwardSchedule } from '../award/cycleTransitions'
import { startDueCommittees } from '../committee/committeeRelease'

export function runAccreditedSchedule() {
  sweepPaidAccreditedPayments()
  runAwardSchedule()
  startDueCommittees()
  settleExpiredCompletions()
}
