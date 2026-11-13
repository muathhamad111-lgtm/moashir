import { sweepPaidAccreditedPayments } from './accreditedOpening'
import { settleExpiredCompletions } from './completionExpiry'
import { runAwardSchedule } from '../award/cycleTransitions'
import { startDueCommittees } from '../committee/committeeRelease'
import { sweepPaidPathPayments } from '../periodic/paidOpenings'

export function runAccreditedSchedule() {
  sweepPaidAccreditedPayments()
  runAwardSchedule()
  sweepPaidPathPayments()
  startDueCommittees()
  settleExpiredCompletions()
}
